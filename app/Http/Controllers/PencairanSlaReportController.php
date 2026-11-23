<?php

namespace App\Http\Controllers;

use App\Models\PencairanPengajuan;
use App\Services\PencairanSlaReportCsv;
use App\Services\PencairanSlaReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PencairanSlaReportController extends Controller
{
    public const DETAIL_QUERY_KEYS = ['start_date', 'end_date', 'jenis', 'pp', 'q', 'per_page', 'page'];

    public function index(Request $request, PencairanSlaReportService $report)
    {
        abort_unless($request->session()->get('user_role') === 'admin', 403, 'Akses ditolak.');

        $types = ['imprestfund' => 'Imprestfund', 'beban' => 'Beban', 'honor' => 'Honor'];
        $filters = $this->validatedFilters($request);
        $today = CarbonImmutable::today(config('app.timezone'));
        $tab = $filters['tab'] ?? 'overview';
        $filterQuery = array_filter(Arr::except($filters, ['page']), fn ($value) => $value !== null);
        $filterQuery['tab'] = $tab;

        $query = $this->filteredQuery($filters);

        $items = null;
        $summary = [];
        $stageSummary = [];
        if ($tab === 'detail') {
            $items = $query->with('completion')->orderByDesc('first_submitted_at')->orderByDesc('id')
                ->paginate((int) ($filters['per_page'] ?? 25))
                ->appends($filterQuery)
                ->through(fn (PencairanPengajuan $pengajuan) => $report->submission($pengajuan, $today));
        } else {
            $summary = ['total' => 0, 'tepat_waktu' => 0, 'terlambat' => 0, 'undetermined' => 0];
            foreach (PencairanSlaReportService::STAGES as $stage => $label) {
                $stageSummary[$stage] = [
                    'targets' => [],
                    'tepat_waktu' => 0, 'terlambat' => 0, 'data_missing' => 0,
                    'active' => 0, 'active_overdue' => 0,
                ];
            }
            foreach ($query->lazyById(200) as $pengajuan) {
                $row = $report->submission($pengajuan, $today);
                $summary['total']++;
                $summary[$row['result']]++;
                foreach ($row['stages'] as $stage => $measurement) {
                    if ($measurement !== null) {
                        $stageSummary[$stage][$measurement['result']]++;
                        if (isset($measurement['target_days'])) {
                            $stageSummary[$stage]['targets'][$measurement['target_days']] = true;
                        }
                    }
                }
                if ($stage = $row['active_stage']) {
                    $stageSummary[$stage]['active']++;
                    if ($row['active']['target_days'] !== null) {
                        $stageSummary[$stage]['targets'][$row['active']['target_days']] = true;
                    }
                    if ($row['active']['status'] === 'terlambat') {
                        $stageSummary[$stage]['active_overdue']++;
                    }
                }
            }
            foreach ($stageSummary as &$metrics) {
                ksort($metrics['targets']);
                $measured = $metrics['tepat_waktu'] + $metrics['terlambat'];
                $metrics['compliance'] = $measured > 0 ? round($metrics['tepat_waktu'] / $measured * 100, 1) : null;
            }
            unset($metrics);
        }

        return view('main.pencairan.sla-report', [
            'items' => $items, 'filters' => $filters, 'summary' => $summary,
            'filterQuery' => $filterQuery, 'tab' => $tab, 'types' => $types,
            'stageSummary' => $stageSummary, 'stages' => PencairanSlaReportService::STAGES,
            'activeMenu' => 'sla-report',
        ]);
    }

    public function export(Request $request, PencairanSlaReportService $report, PencairanSlaReportCsv $csv)
    {
        abort_unless($request->session()->get('user_role') === 'admin', 403, 'Akses ditolak.');

        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($filters)->with('completion')
            ->orderByDesc('first_submitted_at')->orderByDesc('id');
        $today = CarbonImmutable::today(config('app.timezone'));

        return response()->streamDownload(function () use ($query, $report, $csv, $today) {
            $csv->write($query->lazy(200)->map(fn (PencairanPengajuan $pengajuan) => $report->submission($pengajuan, $today)));
        }, 'report-sla-detail.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'tab' => ['nullable', Rule::in(['overview', 'detail'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'jenis' => ['nullable', Rule::in(['imprestfund', 'beban', 'honor'])],
            'pp' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = PencairanPengajuan::query()
            ->whereNotNull('first_submitted_at')
            ->with(['histories' => fn ($histories) => $histories->whereIn('action', PencairanSlaReportService::ACTIONS)
                ->select('id', 'pengajuan_id', 'action', 'revision_no', 'created_at')]);

        if (! empty($filters['start_date'])) {
            $query->where('first_submitted_at', '>=', CarbonImmutable::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('first_submitted_at', '<', CarbonImmutable::parse($filters['end_date'])->startOfDay()->addDay());
        }
        if (! empty($filters['jenis'])) {
            $query->where('jenis', $filters['jenis']);
        }
        if (isset($filters['pp']) && trim($filters['pp']) !== '') {
            $query->where('kode_pp', strtoupper(trim($filters['pp'])));
        }
        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $term = '%'.trim($filters['q']).'%';
            $query->where(fn ($search) => $search->where('nomor_pengajuan', 'like', $term)
                ->orWhere('uraian_pengajuan', 'like', $term));
        }

        return $query;
    }
}
