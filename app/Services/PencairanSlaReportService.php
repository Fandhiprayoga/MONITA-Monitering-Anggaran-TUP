<?php

namespace App\Services;

use App\Models\PencairanHistory;
use Carbon\CarbonImmutable;

class PencairanSlaReportService
{
    public const STAGES = [
        'verifikasi' => 'Verifikasi',
        'transaksi_imprestfund' => 'Transaksi Imprestfund',
        'transaksi_beban' => 'Transaksi Beban',
        'transaksi_honor' => 'Transaksi Honor',
    ];

    public function __construct(private PencairanSlaService $sla) {}

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

    public function measure(PencairanHistory $event): array
    {
        if (! in_array($event->action, ['verify', 'complete'], true)) {
            throw new \InvalidArgumentException('Report SLA hanya untuk tahap yang sudah selesai.');
        }

        $pendingSubmit = null;
        $pendingVerify = null;
        foreach ($event->pengajuan->histories as $history) {
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
        $stage = $event->action === 'verify' ? 'verifikasi' : 'transaksi_'.$event->pengajuan->jenis;
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
