<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['002', 'BRI', 'Bank Rakyat Indonesia'],
            ['008', 'MANDIRI', 'Bank Mandiri'],
            ['009', 'BNI', 'Bank Negara Indonesia'],
            ['011', 'DANAMON', 'Bank Danamon Indonesia'],
            ['013', 'PERMATA', 'Bank Permata'],
            ['014', 'BCA', 'Bank Central Asia'],
            ['016', 'MAYBANK', 'Bank Maybank Indonesia'],
            ['019', 'PANIN', 'Bank Panin'],
            ['022', 'CIMB', 'Bank CIMB Niaga'],
            ['023', 'UOB', 'Bank UOB Indonesia'],
            ['028', 'OCBC', 'Bank OCBC NISP'],
            ['042', 'MUFG', 'MUFG Bank'],
            ['046', 'DBS', 'Bank DBS Indonesia'],
            ['050', 'SCB', 'Standard Chartered Bank'],
            ['076', 'BBA', 'Bank Bumi Arta'],
            ['087', 'HSBC', 'Bank HSBC Indonesia'],
            ['095', 'JTRUST', 'Bank JTrust Indonesia'],
            ['097', 'MAYAPADA', 'Bank Mayapada'],
            ['110', 'BJB', 'Bank Pembangunan Daerah Jawa Barat dan Banten'],
            ['111', 'DKI / JAKARTA', 'Bank DKI / Bank Jakarta'],
            ['112', 'BPD DIY', 'Bank Pembangunan Daerah Daerah Istimewa Yogyakarta'],
            ['113', 'JATENG', 'Bank Pembangunan Daerah Jawa Tengah'],
            ['114', 'JATIM', 'Bank Pembangunan Daerah Jawa Timur'],
            ['116', 'ACEH', 'Bank Aceh Syariah'],
            ['147', 'MUAMALAT', 'Bank Muamalat Indonesia'],
            ['200', 'BTN', 'Bank Tabungan Negara'],
            ['451', 'BSI', 'Bank Syariah Indonesia'],
            ['490', 'BNC', 'Bank Neo Commerce'],
            ['501', 'BCA DIGITAL', 'Bank Digital BCA (blu)'],
            ['535', 'SEABANK', 'SeaBank Indonesia'],
            ['542', 'JAGO', 'Bank Jago'],
            ['547', 'BTPNS', 'Bank BTPN Syariah'],
            ['567', 'ALLO', 'Allo Bank Indonesia'],
        ];

        foreach ($banks as [$kodeBank, $inisial, $namaBank]) {
            Bank::firstOrCreate(
                ['kode_bank' => $kodeBank],
                ['inisial' => $inisial, 'nama_bank' => $namaBank, 'status' => 'aktif'],
            );
        }
    }
}
