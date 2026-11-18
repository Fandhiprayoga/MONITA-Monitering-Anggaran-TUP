<?php

namespace App\Http\Controllers;

use App\Models\PencairanHistory;
use App\Services\PencairanSlaReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PencairanSlaReportController extends Controller
{
    public function index(Request $request, PencairanSlaReportService $report)
    {
        abort_unless($request->session()->get('user_role') === 'admin', 403, 'Akses ditolak.');

        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'tahap' => ['nullable', Rule::in(array_keys(PencairanSlaReportService::STAGES))],
            'hasil' => ['nullable', Rule::in(['tepat_waktu', 'terlambat', 'data_missing'])],
            'pp' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = PencairanHistory::query()
            ->whereIn('action', ['verify', 'complete'])
            ->with(['pengajuan.histories' => fn ($histories) => $histories->whereIn('action', ['submit', 'verify', 'return', 'cancel', 'complete'])]);

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['end_date'])->startOfDay()->addDay());
        }
        if (! empty($filters['tahap'])) {
            $query->where('action', $filters['tahap'] === 'verifikasi' ? 'verify' : 'complete');
        }
        $query->whereHas('pengajuan', function ($pengajuan) use ($filters) {
            if (! empty($filters['tahap']) && $filters['tahap'] !== 'verifikasi') {
                $pengajuan->where('jenis', substr($filters['tahap'], strlen('transaksi_')));
            }
            if (isset($filters['pp']) && trim($filters['pp']) !== '') {
                $pengajuan->where('kode_pp', strtoupper(trim($filters['pp'])));
            }
            if (isset($filters['q']) && trim($filters['q']) !== '') {
                $term = '%'.trim($filters['q']).'%';
                $pengajuan->where(fn ($search) => $search->where('nomor_pengajuan', 'like', $term)
                    ->orWhere('uraian_pengajuan', 'like', $term));
            }
        });
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (PencairanHistory $event) => $report->measure($event));
        if (! empty($filters['hasil'])) {
            $rows = $rows->where('result', $filters['hasil']);
        }
        $rows = $rows->values();
        $summarize = static function ($items): array {
            $onTime = $items->where('result', 'tepat_waktu')->count();
            $late = $items->where('result', 'terlambat')->count();
            $measured = $onTime + $late;

            return [
                'total' => $items->count(),
                'on_time' => $onTime,
                'late' => $late,
                'unmeasurable' => $items->where('result', 'data_missing')->count(),
                'compliance' => $measured > 0 ? round($onTime / $measured * 100, 1) : null,
                'average_days' => $measured > 0 ? round($items->whereNotNull('duration_days')->avg('duration_days'), 1) : null,
            ];
        };
        $summary = $summarize($rows);
        $stageSummary = [];
        foreach (PencairanSlaReportService::STAGES as $stage => $label) {
            $stageSummary[$stage] = $summarize($rows->where('stage', $stage))
                + ['target_days' => $report->targetDays($stage)];
        }
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);
        $items = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('main.pencairan.sla-report', [
            'items' => $items, 'filters' => $filters, 'summary' => $summary,
            'stageSummary' => $stageSummary, 'stages' => PencairanSlaReportService::STAGES,
            'activeMenu' => 'sla-report',
        ]);
    }
}
