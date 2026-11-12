<?php

namespace Database\Seeders;

use App\Models\JenisPenghasilan;
use Illuminate\Database\Seeder;

class JenisPenghasilanSeeder extends Seeder
{
    public function run(): void
    {
        $jenisPenghasilan = [
            ['2A', 'Bukan Pegawai Tidak Berkesinambungan', '2.50'],
            ['4A', 'PESERTA KEGIATAN', '5.00'],
            ['5A', 'PEMBERI JASA WAJIB PAJAK LUAR NEGERI', '20.00'],
        ];

        foreach ($jenisPenghasilan as [$kodeJenis, $namaJenisPph, $tarifPph]) {
            JenisPenghasilan::firstOrCreate(
                ['kode_jenis' => $kodeJenis],
                ['nama_jenis_pph' => $namaJenisPph, 'tarif_pph' => $tarifPph],
            );
        }
    }
}
