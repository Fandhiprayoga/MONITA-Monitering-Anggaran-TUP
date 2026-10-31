<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use App\Services\GoogleSheetService;
use App\Services\PencairanService;
use App\Models\PencairanPengajuan;
use Illuminate\Http\Request;

class PencairanController extends Controller
{
    public function pengajuan(Request $request)
    {
        return $this->listing($request, 'Pengajuan Pencairan Anggaran', 'pengajuan');
    }

    public function form(string $jenis)
    {
        $kodePp = trim((string) Session::get('user_data.kode_pp', ''));
        abort_if($kodePp === '', 403, 'Unit akun belum tersedia. Silakan login kembali.');
        $activeTw = (int) ceil(now()->month / 3);
        $drkOptions = [];
        $drkError = null;
        try {
            $drkOptions = app(GoogleSheetService::class)->getPencairanDrkOptions($kodePp, $activeTw, now()->year);
        } catch (\Exception $exception) {
            report($exception);
            $drkError = 'Gagal memuat DRK dan saldo anggaran. Silakan muat ulang halaman atau hubungi administrator.';
        }

        return view('main.pencairan.form', [
            'jenis'     => $jenis,
            'jenisNama' => ucfirst($jenis),
            'activeTw' => $activeTw,
            'drkOptions' => $drkOptions,
            'drkError' => $drkError,
        ]);
    }

    public function verifikasi(Request $request)
    {
        $this->requireAdmin();

        return $this->listing($request, 'Verifikasi Pengajuan', 'verifikasi');
    }

    public function transaksi(Request $request, string $jenis)
    {
        $this->requireAdmin();

        return $this->listing($request, 'Transaksi '.ucfirst($jenis), 'transaksi', $jenis);
    }

    private function listing(Request $request, string $title, string $mode, ?string $jenis = null)
    {
        $actor = app(PencairanService::class)->actor($request);
        $query = PencairanPengajuan::forActor($actor);
        $allowedStatuses = match ($mode) {
            'verifikasi' => ['submitted', 'returned', 'cancelled'],
            'transaksi' => ['verified', 'completed'],
            default => ['draft', 'submitted', 'returned', 'verified', 'completed', 'cancelled'],
        };
        $query->whereIn('status', $allowedStatuses);
        if ($jenis) { $query->where('jenis', $jenis); }
        if (in_array($request->jenis, ['impresfund', 'beban', 'honor'], true)) { $query->where('jenis', $request->jenis); }
        $defaultStatus = $mode === 'verifikasi' ? 'submitted' : ($mode === 'transaksi' ? 'verified' : '');
        $status = $request->query('status', $defaultStatus);
        if (in_array($status, $allowedStatuses, true)) { $query->where('status', $status); }
        if ($request->filled('tahun') && preg_match('/^\d{4}$/', (string) $request->tahun)) { $query->where('tahun_anggaran', $request->tahun); }
        if (in_array((string) $request->tw, ['1', '2', '3', '4'], true)) { $query->where('triwulan', $request->tw); }
        if ($request->filled('q')) {
            $term = '%'.mb_substr((string) $request->q, 0, 100).'%';
            $query->where(fn ($q) => $q->where('nomor_pengajuan', 'like', $term)->orWhere('uraian_pengajuan', 'like', $term));
        }
        if ($actor['role'] === 'admin' && $request->filled('pp')) { $query->where('kode_pp', strtoupper(trim((string) $request->pp))); }
        $mode === 'verifikasi' ? $query->orderBy('last_submitted_at') : $query->latest('updated_at');
        return view('main.pencairan.pengajuan', ['items' => $query->paginate(20)->withQueryString(),
            'title' => $title, 'mode' => $mode, 'statusFilter' => $status, 'allowedStatuses' => $allowedStatuses]);
    }

    public function show(Request $request, PencairanPengajuan $pengajuan)
    {
        $actor = app(PencairanService::class)->authorize($request, $pengajuan);
        $pengajuan->load([$pengajuan->jenis.'Details', 'histories', 'revisions', 'completion.attachments']);
        $pending = PencairanPengajuan::where('kode_pp', $pengajuan->kode_pp)
            ->where('drk_source_id', $pengajuan->drk_source_id)->where('tahun_anggaran', $pengajuan->tahun_anggaran)
            ->where('triwulan', $pengajuan->triwulan)->whereIn('status', ['submitted', 'verified'])->sum('total_bruto');
        $displayRows = $pengajuan->detailRows();
        if ($pengajuan->jenis === 'impresfund') {
            $displayRows = $pengajuan->impresfundDetails->map(function ($detail) {
                $row = $detail->raw_data;
                $row['subtotal'] = $detail->subtotal_efektif === null ? 'Belum dihitung'
                    : \App\Services\PencairanDecimal::format($detail->subtotal_efektif);
                return $row;
            })->all();
        }
        return view('main.pencairan.show', compact('pengajuan', 'actor', 'pending', 'displayRows'));
    }

    public function edit(Request $request, PencairanPengajuan $pengajuan)
    {
        $actor = app(PencairanService::class)->authorize($request, $pengajuan);
        abort_unless($actor['kode_pp'] !== '' && $pengajuan->kode_pp === $actor['kode_pp'] && in_array($pengajuan->status, ['draft', 'returned'], true), 403);
        $options = []; $error = null;
        try { $options = app(GoogleSheetService::class)->getPencairanDrkOptions($pengajuan->kode_pp, $pengajuan->triwulan, $pengajuan->tahun_anggaran); }
        catch (\Throwable $e) { report($e); $error = 'Gagal memuat DRK dan saldo. Draft tetap dapat disimpan; submit memerlukan data sumber terkini.'; }
        return view('main.pencairan.form', ['jenis' => $pengajuan->jenis, 'jenisNama' => ucfirst($pengajuan->jenis),
            'activeTw' => $pengajuan->triwulan, 'drkOptions' => $options, 'drkError' => $error, 'pengajuan' => $pengajuan]);
    }

    private function requireAdmin(): void
    {
        if (Session::get('user_role') !== 'admin') {
            abort(403, 'Akses ditolak.');
        }
    }
}
