<?php

namespace Tests\Feature;

use App\Enums\PencairanStatus;
use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use App\Services\PencairanService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PencairanPengajuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PencairanPengajuanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_consistent_details_and_workflow_without_sheets(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(0, 0));
        $this->mock(GoogleSheetService::class)->shouldNotReceive('getPencairanDrkOptions');
        $this->seed(PencairanPengajuanSeeder::class);

        $this->assertDatabaseCount('pengajuans', 54);
        $this->assertDatabaseCount('pengajuan_actors', 4);
        $this->assertDatabaseCount('pengajuan_revisions', 45);
        $this->assertDatabaseCount('pengajuan_completions', 9);
        foreach (['impresfund', 'beban', 'honor'] as $jenis) {
            $this->assertDatabaseCount('pengajuan_'.$jenis.'_details', 54);
            foreach (PencairanStatus::cases() as $status) {
                $this->assertSame(3, PencairanPengajuan::where('jenis', $jenis)->where('status', $status->value)->count());
            }
        }

        foreach (PencairanPengajuan::all() as $p) {
            $this->assertCount(3, $p->detailRows());
            [, $totals] = app(PencairanService::class)->calculate($p->jenis, $p->detailRows(), true);
            foreach ($totals as $field => $value) {
                $this->assertSame($value, $p->{$field});
            }
            $this->assertTrue($p->header_snapshot['dummy']);
            $this->assertTrue($p->created_at->lessThanOrEqualTo(now()));
            $this->assertSame('001', $p->detailRows()[0]['no']);
            if ($p->status === 'draft') {
                $this->assertNull($p->nomor_pengajuan);
                $this->assertCount(0, $p->revisions);
            } else {
                $this->assertNotNull($p->nomor_pengajuan);
                $this->assertCount(1, $p->revisions);
                $this->assertSame($p->detailRows(), $p->revisions->first()->snapshot['details']);
            }
            $this->assertSame($p->status, $p->histories->last()->to_status);
            $this->assertSame($p->lock_version + 1, $p->histories->count());
            if ($p->status === 'completed') {
                $this->assertSame($p->verified_at->toDateString(), $p->completion->completion_date->toDateString());
                $this->assertTrue($p->completed_at->lessThanOrEqualTo(now()));
            }
            if ($p->jenis !== 'impresfund') {
                $this->assertSame('0000000001', $p->detailRows()[0]['rekening']);
            }
        }
        $this->assertSame(45, (int) DB::table('pengajuan_number_sequences')->where('year', 2026)->value('last_number'));
    }

    public function test_repeated_database_seeding_preserves_existing_data_and_number_sequence(): void
    {
        $this->seed(DatabaseSeeder::class);
        $p = PencairanPengajuan::where('status', 'draft')->firstOrFail();
        $p->update(['status' => 'cancelled']);
        $before = DB::table('pengajuans')->orderBy('id')->get()->toJson();
        $sequence = DB::table('pengajuan_number_sequences')->first()->last_number;

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pengajuans', 54);
        $this->assertSame($before, DB::table('pengajuans')->orderBy('id')->get()->toJson());
        $this->assertSame($sequence, DB::table('pengajuan_number_sequences')->first()->last_number);
    }

    public function test_seeder_preserves_other_submissions_and_continues_their_numbers(): void
    {
        $actor = \App\Models\PencairanActor::create([
            'source_key' => hash('sha256', 'existing-actor'), 'username' => 'existing',
            'name' => 'Existing User', 'role' => 'user',
        ]);
        $existing = PencairanPengajuan::create([
            'jenis' => 'beban', 'status' => 'submitted', 'kode_pp' => 'OTHER', 'nama_pp' => 'Unit lain',
            'created_by_actor_id' => $actor->id, 'tahun_anggaran' => now()->year,
            'triwulan' => (int) ceil(now()->month / 3), 'tahun_nomor' => now()->year,
            'nomor_urut' => 120, 'nomor_pengajuan' => '000120/'.now()->year.'/'.now()->format('dm').'/OTHER',
            'uraian_pengajuan' => 'Pengajuan yang sudah ada',
        ]);
        $before = $existing->fresh()->toArray();
        DB::table('pengajuan_number_sequences')->insert(['year' => now()->year, 'last_number' => 100]);

        $this->seed(PencairanPengajuanSeeder::class);

        $this->assertDatabaseCount('pengajuans', 55);
        $this->assertSame($before, $existing->fresh()->toArray());
        $this->assertSame(165, (int) DB::table('pengajuan_number_sequences')->where('year', now()->year)->value('last_number'));
        $this->assertSame(121, (int) PencairanPengajuan::where('id', '!=', $existing->id)->min('nomor_urut'));
    }

    public function test_dummy_seeder_refuses_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\LogicException::class);
        $this->seed(PencairanPengajuanSeeder::class);
    }
}