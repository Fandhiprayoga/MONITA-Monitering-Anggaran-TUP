<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
        });

        DB::table('pengajuans')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($pengajuans): void {
            foreach ($pengajuans as $pengajuan) {
                DB::table('pengajuans')->where('id', $pengajuan->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
