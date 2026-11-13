<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImprestfundTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_13_000001_align_imprestfund_detail_table.php');
    }

    public function test_existing_table_is_renamed_without_losing_details(): void
    {
        $actor = PencairanActor::create([
            'source_key' => 'migration-test', 'username' => 'test', 'name' => 'Test', 'role' => 'user',
        ]);
        $pengajuan = PencairanPengajuan::create([
            'jenis' => 'imprestfund', 'status' => 'draft', 'kode_pp' => 'PP01', 'nama_pp' => 'PP',
            'created_by_actor_id' => $actor->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
        ]);
        $pengajuan->details()->create(['urutan' => 1, 'raw_data' => ['uraian' => 'Test'], 'uraian' => 'Test']);
        Schema::rename('pengajuan_imprestfund_details', 'pengajuan_impresfund_details');

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('pengajuan_impresfund_details'));
        $this->assertTrue(Schema::hasTable('pengajuan_imprestfund_details'));
        $this->assertDatabaseHas('pengajuan_imprestfund_details', ['pengajuan_id' => $pengajuan->id, 'uraian' => 'Test']);
        $this->migration()->up();
        $this->migration()->down();
        $this->assertTrue(Schema::hasTable('pengajuan_imprestfund_details'));
    }

    public function test_migration_refuses_old_pengajuan_kind(): void
    {
        $actor = PencairanActor::create([
            'source_key' => 'migration-test', 'username' => 'test', 'name' => 'Test', 'role' => 'user',
        ]);
        DB::table('pengajuans')->insert([
            'jenis' => 'impresfund', 'status' => 'draft', 'kode_pp' => 'PP01', 'nama_pp' => 'PP',
            'created_by_actor_id' => $actor->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
        ]);
        Schema::rename('pengajuan_imprestfund_details', 'pengajuan_impresfund_details');

        try {
            $this->migration()->up();
            $this->fail('Migrasi harus menolak pengajuan dengan jenis lama.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('jenis impresfund masih ada', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('pengajuan_impresfund_details'));
        $this->assertFalse(Schema::hasTable('pengajuan_imprestfund_details'));
    }

    public function test_migration_refuses_when_both_tables_exist(): void
    {
        Schema::create('pengajuan_impresfund_details', function ($table) {
            $table->id();
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Skema tabel detail Imprestfund tidak konsisten');
        $this->migration()->up();
    }
}
