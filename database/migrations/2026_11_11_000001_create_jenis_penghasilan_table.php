<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_penghasilan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jenis', 30)->unique();
            $table->string('nama_jenis_pph');
            $table->decimal('tarif_pph', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_penghasilan');
    }
};
