<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use App\Services\GoogleSheetService;

class PencairanController extends Controller
{
    public function pengajuan()
    {
        return view('main.pencairan.pengajuan');
    }

    public function form(string $jenis)
    {
        $kodePp = trim((string) Session::get('user_data.kode_pp', ''));
        abort_if($kodePp === '', 403, 'Unit akun belum tersedia. Silakan login kembali.');
        $activeTw = (int) ceil(now()->month / 3);
        $drkOptions = [];
        $drkError = null;
        try {
            $drkOptions = app(GoogleSheetService::class)->getUnitDrkOptions($kodePp, $activeTw);
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

    public function verifikasi()
    {
        $this->requireAdmin();

        return view('main.pencairan.verifikasi');
    }

    public function transaksi(string $jenis)
    {
        $this->requireAdmin();

        return view('main.pencairan.transaksi', [
            'jenis'     => $jenis,
            'jenisNama' => ucfirst($jenis),
        ]);
    }

    private function requireAdmin(): void
    {
        if (Session::get('user_role') !== 'admin') {
            abort(403, 'Akses ditolak.');
        }
    }
}
