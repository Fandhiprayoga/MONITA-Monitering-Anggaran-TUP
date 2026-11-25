<?php

namespace App\Services;

use App\Models\PencairanPengajuan;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

class PencairanAdminDashboardService
{
    public function __construct(
        private PencairanSlaReportService $report,
        private PencairanDashboardService $budget,
        private GoogleSheetService $sheets,
    ) {}

    public function statistics(int $year, array $quarters, ?string $unit): array
    {
        $counts = [
            'masuk' => 0, 'returned' => 0, 'submitted' => 0,
            'imprestfund_queue' => 0, 'beban_queue' => 0, 'honor_queue' => 0,
            'total' => 0, 'cancelled' => 0, 'imprestfund' => 0, 'beban' => 0, 'honor' => 0,
        ];
        $stages = [];
        foreach (PencairanSlaReportService::STAGES as $stage => $label) {
            $stages[$stage] = ['tepat_waktu' => 0, 'terlambat' => 0, 'data_missing' => 0, 'duration' => 0];
        }
        $query = PencairanPengajuan::withoutDummy()
            ->where('tahun_anggaran', $year)->whereIn('triwulan', $quarters)
            ->whereNotNull('first_submitted_at')->where('status', '!=', 'draft')
            ->with(['histories' => fn ($histories) => $histories->whereIn('action', PencairanSlaReportService::ACTIONS)
                ->select('id', 'pengajuan_id', 'action', 'revision_no', 'created_at')]);
        if ($unit !== null) {
            $query->where('kode_pp', $unit);
        }

        $today = CarbonImmutable::today(config('app.timezone'));
        foreach ($query->lazyById(200) as $pengajuan) {
            $counts['masuk']++;
            $counts['total']++;
            if ($pengajuan->status === 'cancelled') {
                $counts['cancelled']++;
            } else {
                $counts[$pengajuan->jenis]++;
            }
            if (in_array($pengajuan->status, ['submitted', 'returned'], true)) {
                $counts[$pengajuan->status]++;
            } elseif ($pengajuan->status === 'verified') {
                $counts[$pengajuan->jenis.'_queue']++;
            }
            $measurements = $this->report->submission($pengajuan, $today)['stages'];
            foreach ($measurements as $stage => $measurement) {
                if ($measurement === null) {
                    continue;
                }
                $stages[$stage][$measurement['result']]++;
                if ($measurement['duration_days'] !== null) {
                    $stages[$stage]['duration'] += $measurement['duration_days'];
                }
            }
        }
        foreach ($stages as &$stage) {
            $valid = $stage['tepat_waktu'] + $stage['terlambat'];
            $stage['percent'] = $valid ? round($stage['tepat_waktu'] * 100 / $valid, 1) : null;
            $stage['average'] = $valid ? round($stage['duration'] / $valid, 1) : null;
        }
        unset($stage);

        return ['counts' => $counts, 'stages' => $stages];
    }

    public function budget(array $actor, array $units, int $year, array $quarters, bool $allUnits): array
    {
        if ($units === []) {
            throw new \DomainException('Daftar unit tidak tersedia pada spreadsheet.');
        }
        $unknown = $allUnits && PencairanPengajuan::withoutDummy()
            ->where('tahun_anggaran', $year)->whereIn('triwulan', $quarters)
            ->whereIn('status', ['submitted', 'verified', 'completed', 'returned'])
            ->whereNotIn('kode_pp', array_keys($units))->exists();
        if ($unknown) {
            throw new \DomainException('Terdapat pengajuan dari unit yang tidak tersedia dalam daftar spreadsheet. Periksa daftar unit dan filter yang dipilih.');
        }

        $options = [];
        foreach ($quarters as $quarter) {
            $options[$quarter] = $this->sheets->getPencairanDrkOptionsForUnits(array_keys($units), $quarter, $year);
        }
        $rows = [];
        $totals = array_fill_keys(['pagu', 'realisasi', 'proses', 'saldo'], BigDecimal::zero());
        foreach ($units as $code => $name) {
            $perQuarter = [];
            foreach ($quarters as $quarter) {
                $perQuarter[$quarter] = $options[$quarter][$code];
            }
            $data = $this->budget->calculate($actor, $code, $year, $quarters, $perQuarter);
            foreach ($data['rows'] as $row) {
                $rows[] = ['unit' => $code.' - '.$name] + $row;
            }
            foreach ($totals as $key => $total) {
                $totals[$key] = $total->plus($data['totals'][$key]);
            }
        }
        return compact('rows', 'totals');
    }
}
