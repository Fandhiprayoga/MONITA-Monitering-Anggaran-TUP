<?php

namespace App\Services;

use App\Models\PencairanHistory;
use App\Models\PencairanPengajuan;
use Carbon\CarbonImmutable;

class PencairanSlaReportService
{
    public const STAGES = [
        'verifikasi' => 'Verifikasi',
        'transaksi_imprestfund' => 'Transaksi Imprestfund',
        'transaksi_beban' => 'Transaksi Beban',
        'transaksi_honor' => 'Transaksi Honor',
    ];

    public const ACTIONS = ['submit', 'verify', 'return', 'cancel', 'complete'];

    public function __construct(private PencairanSlaService $sla) {}

    public function submission(PencairanPengajuan $pengajuan, CarbonImmutable $today): array
    {
        $transactionStage = 'transaksi_'.$pengajuan->jenis;
        $activeStage = match ($pengajuan->status) {
            'submitted' => 'verifikasi',
            'verified' => $transactionStage,
            default => null,
        };
        $active = $activeStage !== null ? $this->sla->measure($pengajuan, $today) : null;
        $stages = [
            'verifikasi' => $this->latestMeasurement($pengajuan, 'verify', in_array($pengajuan->status, ['verified', 'completed'], true)),
            $transactionStage => $this->latestMeasurement($pengajuan, 'complete', $pengajuan->status === 'completed'),
        ];
        $results = array_column($stages, 'result');
        $result = match (true) {
            in_array('terlambat', $results, true) => 'terlambat',
            $pengajuan->status === 'completed' && $results === ['tepat_waktu', 'tepat_waktu'] => 'tepat_waktu',
            default => 'undetermined',
        };

        return [
            'pengajuan' => $pengajuan,
            'stages' => $stages,
            'transaction_stage' => $transactionStage,
            'active_stage' => $activeStage,
            'active' => $active,
            'result' => $result,
        ];
    }

    private function latestMeasurement(PencairanPengajuan $pengajuan, string $action, bool $expected): ?array
    {
        $event = $pengajuan->histories->last(fn (PencairanHistory $history) => $history->action === $action);
        if ($event) {
            return $this->measure($pengajuan, $event);
        }

        return $expected ? [
            'result' => 'data_missing', 'duration_days' => null, 'finished_at' => null,
            'problem' => 'Riwayat selesai tahap tidak tersedia.',
        ] : null;
    }

    public function targetDays(string $stage): int
    {
        if (! array_key_exists($stage, self::STAGES)) {
            throw new \InvalidArgumentException('Tahap report SLA tidak dikenal.');
        }

        return $this->sla->targetDays(
            $stage === 'verifikasi' ? 'verifikasi' : 'transaksi',
            $stage === 'verifikasi' ? '' : substr($stage, strlen('transaksi_'))
        );
    }

    private function measure(PencairanPengajuan $pengajuan, PencairanHistory $event): array
    {
        if (! in_array($event->action, ['verify', 'complete'], true)) {
            throw new \InvalidArgumentException('Report SLA hanya untuk tahap yang sudah selesai.');
        }

        $pendingSubmit = null;
        $pendingVerify = null;
        foreach ($pengajuan->histories as $history) {
            if ($history->id >= $event->id) {
                break;
            }
            switch ($history->action) {
                case 'submit':
                    $pendingSubmit = $history;
                    $pendingVerify = null;
                    break;
                case 'verify':
                    $pendingSubmit = null;
                    $pendingVerify = $history;
                    break;
                case 'return':
                case 'cancel':
                case 'complete':
                    $pendingSubmit = null;
                    $pendingVerify = null;
                    break;
            }
        }
        $start = $event->action === 'verify' ? $pendingSubmit : $pendingVerify;
        $stage = $event->action === 'verify' ? 'verifikasi' : 'transaksi_'.$pengajuan->jenis;
        $targetDays = $this->targetDays($stage);
        $problem = null;
        if (! $start || (string) $start->revision_no !== (string) $event->revision_no) {
            $start = null;
            $problem = 'Riwayat awal pada siklus yang sama tidak tersedia.';
        } elseif ($event->created_at->lessThan($start->created_at)) {
            $problem = 'Waktu selesai mendahului waktu mulai. Periksa riwayat pengajuan.';
        }

        $measurement = $problem === null
            ? $this->sla->completed($start->created_at, $event->created_at, $targetDays)
            : ['due_date' => null, 'duration_days' => null, 'overdue_days' => null, 'result' => 'data_missing'];

        return [
            'event' => $event,
            'stage' => $stage,
            'started_at' => $start ? CarbonImmutable::instance($start->created_at) : null,
            'finished_at' => CarbonImmutable::instance($event->created_at),
            'target_days' => $targetDays,
            'problem' => $problem,
        ] + $measurement;
    }
}
