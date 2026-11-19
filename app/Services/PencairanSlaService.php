<?php

namespace App\Services;

use App\Models\PencairanPengajuan;
use App\Models\PencairanSlaPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class PencairanSlaService
{
    public const STAGES = [
        'verifikasi' => 'Verifikasi',
        'transaksi_imprestfund' => 'Transaksi Imprestfund',
        'transaksi_beban' => 'Transaksi Beban',
        'transaksi_honor' => 'Transaksi Honor',
    ];

    public const INITIAL_TARGETS = [
        'verifikasi' => 2,
        'transaksi_imprestfund' => 2,
        'transaksi_beban' => 5,
        'transaksi_honor' => 5,
    ];

    /** @var Collection<int, PencairanSlaPolicy>|null */
    private ?Collection $policies = null;

    public function measure(PencairanPengajuan $pengajuan, ?CarbonInterface $now = null): array
    {
        $stage = match ($pengajuan->status) {
            'submitted' => 'verifikasi',
            'verified' => 'transaksi',
            default => null,
        };
        $startedAt = match ($stage) {
            'verifikasi' => $pengajuan->last_submitted_at,
            'transaksi' => $pengajuan->verified_at,
            default => null,
        };
        if ($stage === null) {
            throw new \InvalidArgumentException('SLA hanya dapat dihitung untuk pengajuan yang sedang menunggu proses.');
        }
        if ($startedAt === null) {
            return [
                'stage' => $stage,
                'started_at' => null,
                'target_days' => null,
                'due_date' => null,
                'status' => 'data_missing',
                'remaining_days' => null,
                'overdue_days' => null,
            ];
        }

        $targetDays = $this->targetDays($stage === 'verifikasi' ? $stage : 'transaksi_'.$pengajuan->jenis, $startedAt);
        $startDate = CarbonImmutable::parse($startedAt)->startOfDay();
        $dueDate = $this->addBusinessDays($startDate, $targetDays);
        $today = CarbonImmutable::instance($now ?? CarbonImmutable::now())->startOfDay();
        $remainingDays = $today->lessThanOrEqualTo($dueDate)
            ? $this->businessDaysBetween($today, $dueDate)
            : null;
        $overdueDays = $today->greaterThan($dueDate)
            ? $this->businessDaysBetween($dueDate, $today)
            : null;
        $status = $today->greaterThan($dueDate)
            ? 'terlambat'
            : ($remainingDays <= 1 ? 'mendekati' : 'aman');

        return [
            'stage' => $stage,
            'started_at' => $startDate,
            'target_days' => $targetDays,
            'due_date' => $dueDate,
            'status' => $status,
            'remaining_days' => $remainingDays,
            'overdue_days' => $overdueDays,
        ];
    }

    public function targetsOn(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'))->toDateString();
        $this->policies ??= PencairanSlaPolicy::query()->orderByDesc('effective_from')->get();
        $policy = $this->policies->first(fn (PencairanSlaPolicy $policy) => $policy->effective_from <= $day);

        return $policy?->only(array_keys(self::STAGES)) ?? self::INITIAL_TARGETS;
    }

    public function targetDays(string $stage, CarbonInterface $startedAt): int
    {
        return $this->targetsOn($startedAt)[$stage] ?? throw new \InvalidArgumentException('Tahap SLA tidak dikenal.');
    }

    public function completed(CarbonInterface $start, CarbonInterface $end, int $targetDays): array
    {
        if ($end->lessThan($start)) {
            throw new \InvalidArgumentException('Waktu selesai SLA tidak boleh sebelum waktu mulai.');
        }

        $startDate = CarbonImmutable::instance($start)->startOfDay();
        $endDate = CarbonImmutable::instance($end)->startOfDay();
        $dueDate = $this->addBusinessDays($startDate, $targetDays);

        return [
            'due_date' => $dueDate,
            'duration_days' => $this->businessDaysBetween($startDate, $endDate),
            'overdue_days' => $endDate->greaterThan($dueDate) ? $this->businessDaysBetween($dueDate, $endDate) : 0,
            'result' => $endDate->greaterThan($dueDate) ? 'terlambat' : 'tepat_waktu',
        ];
    }

    private function addBusinessDays(CarbonImmutable $date, int $days): CarbonImmutable
    {
        $added = 0;
        while ($added < $days) {
            $date = $date->addDay();
            if ($date->isWeekday()) {
                $added++;
            }
        }

        return $date;
    }

    private function businessDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $days = 0;
        while ($from->lessThan($to)) {
            $from = $from->addDay();
            if ($from->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }
}
