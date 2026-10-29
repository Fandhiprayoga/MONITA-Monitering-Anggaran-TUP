<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Traits\FormatDataTrait;

class GoogleSheetService
{
    use FormatDataTrait;

    private $service;
    private $spreadsheetId;

    public function __construct()
    {
        $this->spreadsheetId = env('GOOGLE_SPREADSHEET_ID', '');
        
        try {
            $client = new Client();
            $client->setApplicationName('Monita System');
            $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
            $client->setAuthConfig(storage_path('app/credentials/service-account.json'));
            
            $this->service = new Sheets($client);
        } catch (\Exception $e) {
            Log::error('Google Sheets Service initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUnitDrkOptions(string $kodePp, int $triwulan): array
    {
        if (trim($kodePp) === '' || $triwulan < 1 || $triwulan > 4) {
            throw new \InvalidArgumentException('Unit atau triwulan tidak valid.');
        }
        if (empty($this->spreadsheetId)) {
            throw new \RuntimeException('Sumber spreadsheet anggaran belum dikonfigurasi.');
        }

        $range = 'RAW Data TW '.$this->toRoman($triwulan).'!B2:N3000';
        $response = $this->service->spreadsheets_values->get(
            $this->spreadsheetId,
            $range,
            ['valueRenderOption' => 'UNFORMATTED_VALUE']
        );
        $options = [];
        foreach ($response->getValues() ?? [] as $index => $row) {
            if (strtoupper(trim((string) ($row[0] ?? ''))) !== strtoupper(trim($kodePp))) {
                continue;
            }
            $kodeDrk = trim((string) ($row[2] ?? ''));
            if ($kodeDrk === '') {
                continue;
            }
            $options[] = [
                'id' => (string) ($index + 2),
                'tipe' => trim((string) ($row[1] ?? '')),
                'drk_tup' => $kodeDrk,
                'kode_drk' => trim((string) ($row[12] ?? '')),
                'kode_pp' => trim((string) ($row[9] ?? '')),
                'nama_pp' => trim((string) ($row[10] ?? '')),
                'kode_akun' => trim((string) ($row[3] ?? '')),
                'nama_akun' => trim((string) ($row[4] ?? '')),
                'uraian_drk' => trim((string) ($row[5] ?? '')),
                'saldo_awal' => number_format($this->parseNumber($row[6] ?? 0), 2, ',', '.'),
                'anggaran_terpakai' => number_format($this->parseNumber($row[7] ?? 0), 2, ',', '.'),
                'saldo_terkini' => number_format($this->parseNumber($row[8] ?? 0), 2, ',', '.'),
            ];
        }

        return $options;
    }

    /**
     * Ambil data units dari Google Sheets - tab users kolom B dan C mulai dari B3
     */
    public function getUnitsFromSheet()
    {
        return Cache::remember('sidebar_units_data', 3600, function () { // Cache 1 jam
            try {
                if (empty($this->spreadsheetId)) {
                    Log::warning('GOOGLE_SPREADSHEET_ID tidak ditemukan di .env');
                    return config('units', []);
                }

                // Ambil data dari tab "users", kolom B3 sampai C (akhir data)
                // Kolom B = kode_pp, Kolom C = nama_pp
                $range = 'users!B3:C';
                $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
                $values = $response->getValues();

                $units = [];
                
                if (!empty($values)) {
                    foreach ($values as $row) {
                        // Pastikan row memiliki minimal 2 kolom (B = kode_pp, C = nama_pp)
                        // Skip row jika ada kolom yang kosong
                        if (count($row) >= 2 && !empty(trim($row[0])) && !empty(trim($row[1]))) {
                            $code = trim($row[0]);  // Kolom B - kode_pp
                            $name = trim($row[1]);  // Kolom C - nama_pp
                            $units[$code] = $name;
                        }
                    }
                }

                Log::info('Berhasil mengambil data units dari Google Sheets. Total: ' . count($units));
                return $units;

            } catch (\Exception $e) {
                Log::error('Error fetching units from Google Sheets: ' . $e->getMessage());
                
                // Fallback ke config file jika Google Sheets error
                return config('units', []);
            }
        });
    }

    /**
     * Ambil semua data users (untuk kompatibilitas dengan AuthController)
     */
    public function getUsersFromSheet()
    {
        try {
            if (empty($this->spreadsheetId)) {
                return [];
            }

            $range = 'users!A2:F'; // Sesuai dengan range di AuthController
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();

            $users = [];
            if (empty($values)) {
                return $users;
            }

            foreach ($values as $row) {
                $users[] = [
                    'no'        => $row[0] ?? '',
                    'kode_pp'   => $row[1] ?? '',
                    'nama_pp'   => $row[2] ?? '',
                    'username'  => $row[3] ?? '',
                    'password'  => $row[4] ?? '',
                    'role'      => strtolower($row[5] ?? 'user'),
                ];
            }

            return $users;

        } catch (\Exception $e) {
            Log::error('Error fetching users from Google Sheets: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cache units
     */
    public function clearUnitsCache()
    {
        return Cache::forget('sidebar_units_data');
    }

    /**
     * Test koneksi ke Google Sheets
     */
    public function testConnection()
    {
        try {
            $range = 'users!B3:C';
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();
            
            return [
                'success' => true,
                'data_count' => count($values),
                'message' => 'Koneksi berhasil'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}