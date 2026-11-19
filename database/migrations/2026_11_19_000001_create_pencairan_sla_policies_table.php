<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencairan_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->unsignedSmallInteger('verifikasi');
            $table->unsignedSmallInteger('transaksi_imprestfund');
            $table->unsignedSmallInteger('transaksi_beban');
            $table->unsignedSmallInteger('transaksi_honor');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan_sla_policies');
    }
};
