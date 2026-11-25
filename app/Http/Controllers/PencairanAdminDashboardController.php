<?php

namespace App\Http\Controllers;

use App\Models\PencairanPengajuan;
use App\Services\PencairanAdminDashboardService;
use App\Services\PencairanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PencairanAdminDashboardController extends Controller
{
    public function index(Request $request, PencairanService $service)
    {
        $actor = $service->actor($request);
        abort_unless($actor['role'] === 'admin', 403, 'Akses ditolak.');

        $years = PencairanPengajuan::withoutDummy()->distinct()->orderByDesc('tahun_anggaran')
            ->pluck('tahun_anggaran')->map(fn ($year) => (int) $year)->all();
        $years = array_values(array_unique([...$years, now()->year]));
        rsort($years);
        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', Rule::in($years)],
            'tw' => ['nullable', Rule::in(['1', '2', '3', '4', 'tahunan'])],
            'unit' => ['nullable', 'string', 'max:100'],
        ]);
        $units = $service->submissionUnits();
        $unit = strtoupper(trim($filters['unit'] ?? ''));
        abort_if($unit !== '' && !array_key_exists($unit, $units), 422, 'Unit tidak tersedia.');
        $year = (int) ($filters['tahun'] ?? now()->year);
        $period = (string) ($filters['tw'] ?? (int) ceil(now()->month / 3));
        $quarters = $period === 'tahunan' ? [1, 2, 3, 4] : [(int) $period];
        $dashboard = app(PencairanAdminDashboardService::class);
        $statistics = $dashboard->statistics($year, $quarters, $unit !== '' ? $unit : null);
        $budget = null;
        $error = null;
        try {
            $budget = $dashboard->budget($actor, $unit !== '' ? [$unit => $units[$unit]] : $units, $year, $quarters, $unit === '');
        } catch (\DomainException $exception) {
            report($exception);
            $error = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $error = 'Data anggaran tidak dapat dimuat atau dicocokkan. Periksa spreadsheet dan DRK pengajuan, lalu coba lagi.';
        }

        return response()->view('main.pencairan.dashboard-admin', compact(
            'units', 'unit', 'years', 'year', 'period', 'statistics', 'budget', 'error'
        ), $error ? 503 : 200);
    }
}
