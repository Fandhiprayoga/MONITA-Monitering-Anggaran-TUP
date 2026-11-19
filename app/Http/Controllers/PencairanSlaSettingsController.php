<?php

namespace App\Http\Controllers;

use App\Models\PencairanSlaPolicy;
use App\Services\PencairanService;
use App\Services\PencairanSlaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PencairanSlaSettingsController extends Controller
{
    public function index(Request $request, PencairanService $pencairan, PencairanSlaService $sla)
    {
        abort_unless($pencairan->actor($request)['role'] === 'admin', 403, 'Akses ditolak.');

        $today = CarbonImmutable::today(config('app.timezone'));

        return view('main.pencairan.sla-settings', [
            'stages' => PencairanSlaService::STAGES,
            'initialTargets' => PencairanSlaService::INITIAL_TARGETS,
            'activeTargets' => $sla->targetsOn($today),
            'today' => $today,
            'policies' => PencairanSlaPolicy::query()->orderByDesc('effective_from')->paginate(15),
        ]);
    }

    public function store(Request $request, PencairanService $pencairan)
    {
        $actor = $pencairan->actor($request);
        abort_unless($actor['role'] === 'admin', 403, 'Akses ditolak.');

        $today = CarbonImmutable::today(config('app.timezone'))->toDateString();
        $rules = ['effective_from' => ['required', 'date_format:Y-m-d', 'after:'.$today]];
        foreach (PencairanSlaService::STAGES as $stage => $label) {
            $rules[$stage] = ['required', 'integer', 'between:1,365'];
        }
        $validated = $request->validate($rules, [
            'effective_from.after' => 'Tanggal mulai berlaku harus setelah hari ini.',
        ], ['effective_from' => 'tanggal mulai berlaku'] + PencairanSlaService::STAGES);

        $policy = PencairanSlaPolicy::query()->createOrFirst(
            ['effective_from' => $validated['effective_from']],
            $validated + ['created_by' => $actor['username']],
        );
        if (! $policy->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'effective_from' => 'Sudah ada pengaturan SLA untuk tanggal ini. Pilih tanggal lain.',
            ]);
        }

        return redirect()->route('settings.sla.index')->with('success', 'Target SLA baru berhasil dijadwalkan.');
    }
}
