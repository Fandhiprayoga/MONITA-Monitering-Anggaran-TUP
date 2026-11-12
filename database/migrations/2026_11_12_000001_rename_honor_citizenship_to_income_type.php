<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_honor_details', function (Blueprint $table) {
            $table->renameColumn('kewarganegaraan', 'kode_jenis');
        });
        Schema::table('pengajuan_honor_details', function (Blueprint $table) {
            $table->string('kode_jenis', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_honor_details', function (Blueprint $table) {
            $table->renameColumn('kode_jenis', 'kewarganegaraan');
        });
    }
};
