<?php

namespace App\Http\Controllers;

use App\Models\PencairanPengajuan;
use App\Services\PencairanDashboardService;
use App\Services\PencairanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PencairanDashboardController extends Controller
{
    public function index(Request $request, PencairanService $service)
    {
        $actor = $service->actor($request);
        $years = PencairanPengajuan::forActor($actor)->withoutDummy()->distinct()->orderByDesc('tahun_anggaran')
            ->pluck('tahun_anggaran')->map(fn ($year) => (int) $year)->all();
        $years = array_values(array_unique([...$years, now()->year]));
        rsort($years);

        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', Rule::in($years)],
            'tw' => ['nullable', Rule::in(['1', '2', '3', '4', 'tahunan'])],
            'unit' => ['nullable', 'string', 'max:100'],
        ]);
        if ($actor['role'] !== 'admin' && isset($filters['unit'])
            && strtoupper(trim($filters['unit'])) !== $actor['kode_pp']) {
            abort(403, 'Akses PP ditolak.');
        }

        $units = $actor['role'] === 'admin'
            ? $service->submissionUnits()
            : [$actor['kode_pp'] => $actor['nama_pp']];
        $unit = $actor['role'] === 'admin'
            ? strtoupper(trim($filters['unit'] ?? (array_key_exists($actor['kode_pp'], $units) ? $actor['kode_pp'] : (array_key_first($units) ?? ''))))
            : $actor['kode_pp'];
        if ($unit !== '' && !array_key_exists($unit, $units)) {
            abort(422, 'Unit tidak tersedia.');
        }
        $year = (int) ($filters['tahun'] ?? now()->year);
        $period = (string) ($filters['tw'] ?? (int) ceil(now()->month / 3));
        $quarters = $period === 'tahunan' ? [1, 2, 3, 4] : [(int) $period];
        $data = null;
        $error = null;
        if ($unit === '') {
            $error = 'Daftar unit tidak tersedia pada spreadsheet.';
        } else {
            try {
                $data = app(PencairanDashboardService::class)->calculate($actor, $unit, $year, $quarters);
            } catch (\DomainException $exception) {
                report($exception);
                $error = $exception->getMessage();
            } catch (\Throwable $exception) {
                report($exception);
                $error = 'Data anggaran tidak dapat dimuat atau dicocokkan. Periksa sumber spreadsheet dan DRK pengajuan, lalu coba lagi.';
            }
        }

        return response()->view('main.pencairan.dashboard', compact(
            'actor', 'units', 'unit', 'years', 'year', 'period', 'data', 'error'
        ), $error ? 503 : 200);
    }
}
