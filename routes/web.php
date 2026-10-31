<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\PencairanController;
use App\Http\Controllers\PencairanWorkflowController;

// Redirect root ke login
Route::get('/', function () {
    return redirect()->route('login');
});

// Login & logout
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes (pastikan middleware 'auth.spreadsheet' sudah terdaftar di Kernel)
Route::middleware(['auth.spreadsheet'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('pengajuan')->group(function () {

    Route::get('/daftar', function () {
        return view('main.pengajuan.daftar');
    })->name('pengajuan.daftar');

    Route::get('/buat', function () {
        return view('main.pengajuan.buat', [
            'activeTw' => 1
        ]);
    })->name('pengajuan.buat');

    Route::get('/riwayat', function () {
        return view('main.pengajuan.riwayat');
    })->name('pengajuan.riwayat');

});

    Route::prefix('pencairan')->name('pencairan.')->group(function () {
        Route::get('/pengajuan', [PencairanController::class, 'pengajuan'])->name('pengajuan');
        Route::get('/pengajuan/{pengajuan}', [PencairanController::class, 'show'])->whereNumber('pengajuan')->name('show');
        Route::get('/pengajuan/{pengajuan}/edit', [PencairanController::class, 'edit'])->whereNumber('pengajuan')->name('edit');
        Route::get('/lampiran/{attachment}', [PencairanWorkflowController::class, 'attachment'])->whereNumber('attachment')->name('attachment');
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/pengajuan', [PencairanWorkflowController::class, 'store'])->name('store');
            Route::patch('/pengajuan/{pengajuan}', [PencairanWorkflowController::class, 'update'])->whereNumber('pengajuan')->name('update');
            foreach (['submit' => 'submit', 'return' => 'returnToUser', 'verify' => 'verify', 'cancel' => 'cancel', 'complete' => 'complete'] as $action => $method) {
                Route::post('/pengajuan/{pengajuan}/'.$action, [PencairanWorkflowController::class, $method])->whereNumber('pengajuan')->name($action);
            }
        });

        Route::get('/form/{jenis}', [PencairanController::class, 'form'])
            ->whereIn('jenis', ['impresfund', 'beban', 'honor'])
            ->name('form');

        Route::get('/verifikasi', [PencairanController::class, 'verifikasi'])->name('verifikasi');

        Route::get('/transaksi/{jenis}', [PencairanController::class, 'transaksi'])
            ->whereIn('jenis', ['impresfund', 'beban', 'honor'])
            ->name('transaksi');
    });

    Route::get('/unit/{kode}', [UnitController::class, 'show'])->name('unit.show');
    Route::get('/summary/triwulan/{tw}', [SummaryController::class, 'index'])
        ->where('tw', '[1-4]')
        ->name('summary.triwulan');

    Route::get('/laporan/triwulan/{tw}', [LaporanController::class, 'index'])
        ->name('laporan.triwulan');

    Route::get('/management', [ManagementController::class, 'index'])->name('management');
    // Tambah akun
    Route::post('/management/store', [ManagementController::class, 'store'])->name('management.store');
    // Tombol aksi
    Route::get('/management/show/{row}', [ManagementController::class, 'show'])->name('management.show');
    Route::post('/management/update/{row}', [ManagementController::class, 'update'])->name('management.update');
    Route::delete('/management/delete/{row}', [ManagementController::class, 'destroy'])->name('management.destroy');


    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/update', [SettingsController::class, 'update'])->name('settings.update');

    Route::prefix('export')->group(function () {
    Route::get('/laporan-triwulan/{tw}', [ExportController::class, 'laporanTriwulan'])->name('export.laporan-triwulan');
    Route::get('/summary/{tw}/{type}', [ExportController::class, 'summary'])->name('export.summary');
    Route::get('/unit/{kode}', [ExportController::class, 'detailUnit'])->name('export.unit');
    });

});
