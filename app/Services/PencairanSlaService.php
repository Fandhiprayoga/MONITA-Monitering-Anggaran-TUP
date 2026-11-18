<?php

namespace App\Services;

use App\Models\PencairanPengajuan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PencairanSlaService
{
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
        $targetDays = $this->targetDays($stage, $pengajuan->jenis);

        if ($startedAt === null) {
            return [
                'stage' => $stage,
                'started_at' => null,
                'target_days' => $targetDays,
                'due_date' => null,
                'status' => 'data_missing',
                'remaining_days' => null,
                'overdue_days' => null,
            ];
        }

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

    public function targetDays(string $stage, string $jenis): int
    {
        return match ($stage) {
            'verifikasi' => 2,
            'transaksi' => match ($jenis) {
                'imprestfund' => 2,
                'beban', 'honor' => 5,
                default => throw new \InvalidArgumentException('Jenis transaksi SLA tidak dikenal.'),
            },
            default => throw new \InvalidArgumentException('Tahap SLA tidak dikenal.'),
        };
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
