<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Services\GoogleSheetService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleSheetService::class, function ($app) {
            return new GoogleSheetService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Berry uses Bootstrap 5, not Laravel's default Tailwind pagination styles.
        Paginator::useBootstrapFive();
        date_default_timezone_set('Asia/Jakarta');
        // Share data ke semua view
        View::composer('*', function ($view) {
            try {
                // Ambil data units dari Google Sheets
                $googleSheetService = app(GoogleSheetService::class);
                $unitsFromSheet = $googleSheetService->getUnitsFromSheet();
                
                $allUnits = !empty($unitsFromSheet) ? $unitsFromSheet : config('units', []);
                
            } catch (\Exception $e) {
                Log::error('Error loading units from Google Sheets: ' . $e->getMessage());
                $allUnits = config('units', []);
            }

            // Ambil data user dari session
            $userData = session('user_data', []);
            $userRole = session('user_role', 'user');
            $userUnitKode = $userData['kode_pp'] ?? null;
            $userUnitNama = $userData['nama_pp'] ?? null;

            // Cari nama unit dari kode jika tidak ada di session
            if ($userUnitKode && !$userUnitNama && isset($allUnits[$userUnitKode])) {
                $userUnitNama = $allUnits[$userUnitKode];
            }

            $view->with([
                'allUnits'     => $allUnits,
                'userRole'     => $userRole,
                'userUnitKode' => $userUnitKode,
                'userUnitNama' => $userUnitNama,
            ]);
        });
    }
}