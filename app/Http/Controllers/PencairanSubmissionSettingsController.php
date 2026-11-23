<?php

namespace App\Http\Controllers;

use App\Services\PencairanService;
use App\Services\PencairanSubmissionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PencairanSubmissionSettingsController extends Controller
{
    public function index(Request $request, PencairanService $pencairan, PencairanSubmissionSettings $settings)
    {
        abort_unless($pencairan->actor($request)['role'] === 'admin', 403, 'Akses ditolak.');

        return view('main.pencairan.submission-settings', [
            'isOpen' => $settings->isOpen(),
            'histories' => DB::table('pencairan_submission_setting_histories')->orderByDesc('id')->paginate(15),
        ]);
    }

    public function update(Request $request, PencairanService $pencairan, PencairanSubmissionSettings $settings)
    {
        $actor = $pencairan->actor($request);
        abort_unless($actor['role'] === 'admin', 403, 'Akses ditolak.');
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);
        $changed = $settings->change($data['status'] === 'open', $actor['username']);

        return redirect()->route('settings.pencairan.index')->with('success',
            $changed ? 'Status pengajuan berhasil diubah.' : 'Status pengajuan tidak berubah.');
    }
}
