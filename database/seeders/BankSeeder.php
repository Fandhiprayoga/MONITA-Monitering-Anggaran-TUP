<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['0020307', 'BRI', 'BRI', 'aktif'],
            ['0090010', 'BNI', 'BNI', 'aktif'],
            ['0110042', 'BANK DANAMON', 'BANK DANAMON', 'aktif'],
            ['0130475', 'PERMATA', 'PERMATA', 'aktif'],
            ['0139926', 'PERMATA SYARIAH', 'PERMATA SYARIAH', 'aktif'],
            ['0140397', 'BCA', 'BCA', 'aktif'],
            ['0160131', 'MAYBANK INDONESIA', 'MAYBANK INDONESIA', 'aktif'],
            ['0190017', 'PANIN BANK', 'PANIN BANK', 'aktif'],
            ['0220026', 'CIMB NIAGA', 'CIMB NIAGA', 'aktif'],
            ['0229920', 'CIMB NIAGA SYARIAH', 'CIMB NIAGA SYARIAH', 'aktif'],
            ['0230016', 'UOB INDONESIA', 'UOB INDONESIA', 'aktif'],
            ['0280024', 'OCBC NISP', 'OCBC NISP', 'aktif'],
            ['0310305', 'CITIBANK', 'CITIBANK', 'aktif'],
            ['0320308', 'JPMorgan Chase Bank', 'JPMorgan Chase Bank', 'aktif'],
            ['0460307', 'DBS INDONESIA', 'DBS INDONESIA', 'aktif'],
            ['0500306', 'Standard Chartered Bank', 'Standard Chartered Bank', 'aktif'],
            ['0670304', 'DEUTSCHE BANK AG', 'DEUTSCHE BANK AG', 'aktif'],
            ['0870010', 'HSBC INDONESIA', 'HSBC INDONESIA', 'aktif'],
            ['0970017', 'MAYAPADA', 'MAYAPADA', 'aktif'],
            ['1100019', 'BJB', 'BJB', 'aktif'],
            ['1110012', 'BANK DKI JAKARTA', 'BANK DKI JAKARTA', 'aktif'],
            ['1119916', 'DKI SYARIAH', 'DKI SYARIAH', 'aktif'],
            ['1120015', 'BANK BPD DIY', 'BANK BPD DIY', 'aktif'],
            ['1130348', 'BANK JATENG', 'BANK JATENG', 'aktif'],
            ['1140011', 'BANK JATIM', 'BANK JATIM', 'aktif'],
            ['1150014', 'BPD JAMBI', 'BPD JAMBI', 'aktif'],
            ['1169900', 'BANK ACEH SYARIAH', 'BANK ACEH SYARIAH', 'aktif'],
            ['1170010', 'Bank Sumut', 'Bank Sumut', 'aktif'],
            ['1180013', 'BANK NAGARI', 'BANK NAGARI', 'aktif'],
            ['1190016', 'BANK RIAU KEPRI', 'BANK RIAU KEPRI', 'aktif'],
            ['1200017', 'BPD SUMSEL BABEL', 'BPD SUMSEL BABEL', 'aktif'],
            ['1210051', 'BANK LAMPUNG', 'BANK LAMPUNG', 'aktif'],
            ['1220012', 'BANK KALSEL', 'BANK KALSEL', 'aktif'],
            ['1230015', 'BANK KALBAR', 'BANK KALBAR', 'aktif'],
            ['1240018', 'BANK KALTIM', 'BANK KALTIM', 'aktif'],
            ['1240034', 'BANK KALTIMTARA', 'BANK KALTIMTARA', 'aktif'],
            ['1260027', 'BANK SULSELBAR', 'BANK SULSELBAR', 'aktif'],
            ['1280010', 'Bank NTB Syariah', 'Bank NTB Syariah', 'aktif'],
            ['1290013', 'BANK BPD BALI', 'BANK BPD BALI', 'aktif'],
            ['1330012', 'BANK BENGKULU', 'BANK BENGKULU', 'aktif'],
            ['1370017', 'BPD Banten', 'BPD Banten', 'aktif'],
            ['1470011', 'MUAMALAT', 'MUAMALAT', 'aktif'],
            ['1520013', 'Bank Shinhan', 'Bank Shinhan', 'aktif'],
            ['1530016', 'BANK SINARMAS', 'BANK SINARMAS', 'aktif'],
            ['2000150', 'BTN', 'BTN', 'aktif'],
            ['2009928', 'BTN SYARIAH', 'BTN SYARIAH', 'aktif'],
            ['2060100', 'Bank MNC INTERNATIONAL', 'Bank MNC INTERNATIONAL', 'aktif'],
            ['2120001', 'WOORI SAUDARA', 'WOORI SAUDARA SAUDARA INDONESIA 1906', 'aktif'],
            ['2130017', 'BTPN', 'BTPN', 'aktif'],
            ['2130101', 'Bank SMBC Indonesia', 'Bank SMBC Indonesia', 'aktif'],
            ['2530015', 'PT Bank Nano Syariah', 'PT Bank Nano Syariah', 'aktif'],
            ['4220051', 'BSI / BANK SYARIAH INDONESIA', 'BSI / BANK SYARIAH INDONESIA', 'aktif'],
            ['4250018', 'BJB SYARIAH', 'BJB SYARIAH', 'aktif'],
            ['4260121', 'BANK MEGA', 'BANK MEGA', 'aktif'],
            ['4270027', 'BNI SYARIAH', 'BNI SYARIAH', 'nonaktif'],
            ['4410010', 'BUKOPIN', 'BUKOPIN', 'aktif'],
            ['4510017', 'MANDIRI SYARIAH', 'MANDIRI SYARIAH', 'nonaktif'],
            ['4840017', 'BANK KEB HANA', 'BANK KEB HANA', 'aktif'],
            ['4900012', 'BANK YUDHA BHAKTI', 'BANK YUDHA BHAKTI', 'aktif'],
            ['5010011', 'Bank BCA Digital', 'Bank BCA Digital', 'nonaktif'],
            ['5130014', 'BANK INA PERDANA', 'BANK INA PERDANA', 'aktif'],
            ['5170016', 'Bank Panin Syariah', 'Bank Panin Syariah', 'aktif'],
            ['5210031', 'BUKOPIN SYARIAH', 'BUKOPIN SYARIAH', 'aktif'],
            ['5360017', 'BCA SYARIAH', 'BCA SYARIAH', 'aktif'],
            ['5420012', 'BANK ARTOS / BANK JAGO', 'BANK ARTOS / BANK JAGO', 'aktif'],
            ['5670011', 'Allo Bank', 'Allo Bank', 'nonaktif'],
            ['9500307', 'COMMONWEALTH BANK', 'COMMONWEALTH BANK', 'aktif'],
            ['BNI', 'BANK NEGARA INDONESIA 1946', 'BANK NEGARA INDONESIA 1946', 'aktif'],
            ['MDR', 'MANDIRI', 'MANDIRI', 'aktif'],
        ];

        foreach ($banks as [$kodeBank, $inisial, $namaBank, $status]) {
            Bank::withTrashed()->firstOrCreate(
                ['kode_bank' => $kodeBank],
                ['inisial' => $inisial, 'nama_bank' => $namaBank, 'status' => $status],
            );
        }
    }
}
