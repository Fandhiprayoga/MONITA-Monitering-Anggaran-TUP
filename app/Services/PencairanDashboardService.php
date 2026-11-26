<?php

namespace App\Services;

use App\Models\PencairanPengajuan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class PencairanDashboardService
{
    public function __construct(private GoogleSheetService $sheets) {}

    public function calculate(array $actor, string $unit, int $year, array $quarters, ?array $optionsByQuarter = null): array
    {
        $records = PencairanPengajuan::forActor($actor)
            ->withoutDummy()
            ->where('kode_pp', $unit)
            ->where('tahun_anggaran', $year)
            ->whereIn('triwulan', $quarters)
            ->whereIn('status', ['submitted', 'verified', 'completed', 'returned'])
            ->select('triwulan', 'drk_source_id', 'status')
            ->selectRaw('COUNT(*) AS jumlah, SUM(total_bruto) AS nominal')
            ->groupBy('triwulan', 'drk_source_id', 'status')
            ->get();

        $counts = ['total' => 0, 'completed' => 0, 'process' => 0, 'returned' => 0];
        foreach ($records as $record) {
            $counts['total'] += (int) $record->jumlah;
            $key = match ($record->status) {
                'completed' => 'completed',
                'returned' => 'returned',
                default => 'process',
            };
            $counts[$key] += (int) $record->jumlah;
        }

        $rows = [];
        $seen = [];
        foreach ($quarters as $quarter) {
            foreach ($optionsByQuarter === null ? $this->sheets->getPencairanDrkOptions($unit, $quarter, $year) : $optionsByQuarter[$quarter] as $option) {
                $id = $option['id'];
                if (isset($seen[$quarter][$id])) {
                    throw new DomainException("DRK duplikat pada sumber anggaran TW{$quarter}. Hubungi administrator untuk memeriksa spreadsheet.");
                }
                $seen[$quarter][$id] = true;
                if ($option['saldo_awal'] === null) {
                    throw new DomainException("Saldo awal DRK pada TW{$quarter} tidak tersedia atau tidak valid. Hubungi administrator untuk memeriksa spreadsheet.");
                }
                $opening = PencairanDecimal::raw($option['saldo_awal'], 'saldo_awal', true, false);
                if ($opening === null) {
                    throw new DomainException("Saldo awal DRK pada TW{$quarter} tidak tersedia. Hubungi administrator untuk memeriksa spreadsheet.");
                }
                if (!isset($rows[$id])) {
                    $rows[$id] = [
                        'drk' => $option['drk_tup'], 'akun' => $option['kode_akun'],
                        'tipe' => $option['tipe'], 'uraian' => $option['uraian_drk'],
                        'pagu' => BigDecimal::zero(), 'realisasi' => BigDecimal::zero(),
                        'proses' => BigDecimal::zero(),
                    ];
                }
                $rows[$id]['pagu'] = $rows[$id]['pagu']->plus($opening);
            }
        }

        foreach ($records as $record) {
            if ($record->status === 'returned') {
                continue;
            }
            if (!$record->drk_source_id || !isset($seen[$record->triwulan][$record->drk_source_id])) {
                throw new DomainException("DRK pengajuan pada TW{$record->triwulan} tidak ditemukan di spreadsheet. Hubungi administrator untuk melakukan rekonsiliasi DRK.");
            }
            $key = $record->status === 'completed' ? 'realisasi' : 'proses';
            $rows[$record->drk_source_id][$key] = $rows[$record->drk_source_id][$key]->plus((string) $record->nominal);
        }

        $totals = ['pagu' => BigDecimal::zero(), 'realisasi' => BigDecimal::zero(), 'proses' => BigDecimal::zero()];
        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = $totals[$key]->plus($row[$key]);
            }
            $row['saldo'] = $row['pagu']->minus($row['realisasi'])->minus($row['proses']);
        }
        unset($row);
        $totals['saldo'] = $totals['pagu']->minus($totals['realisasi'])->minus($totals['proses']);

        $percentages = null;
        if ($totals['pagu']->isPositive()) {
            $percentages = [];
            foreach ($totals as $key => $total) {
                $percentages[$key] = PencairanDecimal::format((string) $total->multipliedBy(100)
                    ->dividedBy($totals['pagu'], 2, RoundingMode::HALF_UP));
            }
        }

        $chart = null;
        if ($totals['pagu']->isPositive() && !$totals['saldo']->isNegative()
            && !$totals['realisasi']->isNegative() && !$totals['proses']->isNegative()) {
            $chart = [];
            foreach (['realisasi', 'proses', 'saldo'] as $key) {
                $chart[$key] = (string) $totals[$key]->multipliedBy(100)
                    ->dividedBy($totals['pagu'], 4, RoundingMode::HALF_UP);
            }
        }

        return ['rows' => array_values($rows), 'totals' => $totals, 'counts' => $counts, 'chart' => $chart, 'percentages' => $percentages];
    }

    public static function rupiah(BigDecimal $value): string
    {
        [$whole, $fraction] = explode('.', (string) $value->toScale(2));
        $negative = str_starts_with($whole, '-');
        $digits = ltrim($whole, '-');
        return ($negative ? '-' : '').'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $digits).','.$fraction;
    }
}
