<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_actors', function (Blueprint $table) {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('username');
            $table->string('name');
            $table->string('role', 20);
            $table->timestamps();
        });
        Schema::create('pengajuan_number_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('pengajuans', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20);
            $table->string('status', 20)->default('draft');
            $table->string('kode_pp', 100);
            $table->string('nama_pp');
            $table->foreignId('created_by_actor_id')->constrained('pengajuan_actors');
            $table->string('nomor_pengajuan')->nullable()->unique();
            $table->unsignedBigInteger('nomor_urut')->nullable();
            $table->unsignedInteger('tahun_nomor')->nullable();
            $table->unsignedInteger('tahun_anggaran');
            $table->unsignedTinyInteger('triwulan');
            $table->string('drk_source_id', 64)->nullable();
            $table->json('header_snapshot')->nullable();
            $table->text('uraian_pengajuan')->nullable();
            foreach (['total_bruto', 'total_pajak', 'total_neto'] as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            foreach (['saldo_awal_snapshot', 'anggaran_terpakai_snapshot', 'saldo_terkini_snapshot'] as $field) {
                $table->decimal($field, 18, 2)->nullable();
            }
            foreach (['saldo_checked_at', 'first_submitted_at', 'last_submitted_at', 'verified_at', 'completed_at', 'cancelled_at'] as $field) {
                $table->timestamp($field)->nullable();
            }
            foreach (['verified_by', 'completed_by', 'cancelled_by'] as $field) {
                $table->foreignId($field)->nullable()->constrained('pengajuan_actors');
            }
            $table->unsignedInteger('revision_no')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['tahun_nomor', 'nomor_urut']);
            $table->index(['kode_pp', 'status', 'tahun_anggaran', 'triwulan'], 'pengajuan_pp_period');
            $table->index(['status', 'jenis']);
            $table->index(['drk_source_id', 'tahun_anggaran', 'triwulan'], 'pengajuan_drk_period');
        });
        foreach (['impresfund', 'beban', 'honor'] as $jenis) {
            Schema::create('pengajuan_'.$jenis.'_details', function (Blueprint $table) use ($jenis) {
                $table->id();
                $table->foreignId('pengajuan_id')->constrained('pengajuans')->cascadeOnDelete();
                $table->unsignedInteger('urutan');
                $table->json('raw_data');
                if ($jenis === 'impresfund') {
                    $table->text('uraian')->nullable();
                    foreach (['volume', 'tarif', 'subtotal_manual', 'subtotal_efektif'] as $field) {
                        $table->decimal($field, 18, 2)->nullable();
                    }
                } else {
                    foreach (['nominal', 'pajak', 'neto'] as $field) {
                        $table->decimal($field, 18, 2)->nullable();
                    }
                    foreach (['bank', 'nama_rekening', 'nomor_rekening'] as $field) {
                        $table->string($field)->nullable();
                    }
                    if ($jenis === 'honor') {
                        $table->string('kewarganegaraan', 3)->nullable();
                        foreach (['nama_penerima', 'nomor_identitas', 'npwp'] as $field) {
                            $table->string($field)->nullable();
                        }
                    }
                }
                $table->unique(['pengajuan_id', 'urutan']);
            });
        }
        Schema::create('pengajuan_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans');
            $table->unsignedInteger('revision_no');
            $table->foreignId('submitted_by')->constrained('pengajuan_actors');
            $table->timestamp('submitted_at');
            $table->json('snapshot');
            $table->unsignedInteger('schema_version')->default(1);
            $table->unique(['pengajuan_id', 'revision_no']);
        });
        Schema::create('pengajuan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans');
            $table->unsignedInteger('revision_no')->nullable();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('pengajuan_actors');
            $table->string('actor_name');
            $table->string('actor_role', 20);
            $table->text('remark')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('pengajuan_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuans');
            $table->date('completion_date');
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->constrained('pengajuan_actors');
            $table->timestamps();
        });
        Schema::create('pengajuan_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('completion_id')->constrained('pengajuan_completions');
            $table->string('disk');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->constrained('pengajuan_actors');
            $table->timestamps();
        });
        Schema::create('pengajuan_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('pengajuan_actors');
            $table->foreignId('pengajuan_id')->constrained('pengajuans');
            $table->string('operation', 20);
            $table->string('key', 100);
            $table->string('payload_hash', 64);
            $table->json('response');
            $table->timestamps();
            $table->unique(['actor_id', 'pengajuan_id', 'operation', 'key'], 'pengajuan_idempotency_unique');
        });
    }

    public function down(): void
    {
        foreach (['pengajuan_idempotencies', 'pengajuan_attachments', 'pengajuan_completions', 'pengajuan_histories', 'pengajuan_revisions', 'pengajuan_honor_details', 'pengajuan_beban_details', 'pengajuan_impresfund_details', 'pengajuans', 'pengajuan_number_sequences', 'pengajuan_actors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};