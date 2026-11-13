<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $oldTable = 'pengajuan_impresfund_details';
        $newTable = 'pengajuan_imprestfund_details';

        if (DB::table('pengajuans')->where('jenis', 'impresfund')->exists()) {
            throw new \RuntimeException('Pengajuan dengan jenis impresfund masih ada. Migrasikan data lama secara terpisah sebelum melanjutkan.');
        }

        $hasOld = Schema::hasTable($oldTable);
        $hasNew = Schema::hasTable($newTable);

        if ($hasOld && !$hasNew) {
            Schema::rename($oldTable, $newTable);
        } elseif ($hasOld || !$hasNew) {
            throw new \RuntimeException('Skema tabel detail Imprestfund tidak konsisten. Periksa tabel lama dan baru sebelum melanjutkan.');
        }
    }

    public function down(): void
    {
        // The new table may have been created by the original migration.
    }
};
