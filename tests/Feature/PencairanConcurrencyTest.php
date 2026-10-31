<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PencairanConcurrencyTest extends TestCase
{
    public function test_parallel_submit_allocates_global_numbers_and_same_actor_retry_returns_same_result(): void
    {
        if (!function_exists('proc_open')) { $this->markTestSkipped('proc_open is required for concurrency integration.'); }
        $path = tempnam(sys_get_temp_dir(), 'monita-test-');
        $original = config('database.connections.sqlite.database');
        $processes = [];
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
            DB::purge('sqlite');
            // Only this newly-created temporary TEST database is migrated, never the production DB.
            $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
            $actor = PencairanActor::create(['source_key' => 'test', 'username' => 'test', 'name' => 'test', 'role' => 'user']);
            $ids = [];
            foreach (['impresfund', 'beban', 'honor', 'beban'] as $i => $kind) {
                $p = PencairanPengajuan::create(['jenis' => $kind, 'status' => 'draft', 'kode_pp' => 'PP0'.($i + 1), 'nama_pp' => 'Test',
                    'created_by_actor_id' => $actor->id, 'tahun_anggaran' => 2026, 'triwulan' => 4, 'drk_source_id' => str_repeat('a', 64), 'uraian_pengajuan' => 'Test']);
                if ($kind === 'impresfund') {
                    $raw = ['uraian' => 'Test', 'volume' => '1', 'tarif' => '100', 'subtotal' => ''];
                } else {
                    $raw = ['nominal' => '100', 'pajak' => '0', 'neto' => '100', 'bank' => 'Bank', 'nama' => 'Name', 'rekening' => '0001'];
                    if ($kind === 'honor') { $raw += ['kewarganegaraan' => 'WNI', 'penerima' => 'Person', 'identitas' => '0002']; }
                }
                $p->details()->create(['urutan' => 1, 'raw_data' => $raw]); $ids[] = $p->id;
            }
            // Four independent documents plus a simultaneous identical retry on the first.
            foreach (array_merge($ids, [$ids[0]]) as $id) {
                $pipes = [];
                $proc = proc_open([PHP_BINARY, base_path('tests/Support/pencairan-submit-worker.php'), $path, (string) $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($proc); fclose($pipes[0]); $processes[] = [$proc, $pipes];
            }
            $responses = [];
            foreach ($processes as [$proc, $pipes]) {
                $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $this->assertSame(0, proc_close($proc), $err);
                $responses[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            }
            $processes = [];
            $this->assertSame($responses[0], $responses[4]);
            $this->assertSame([1, 2, 3, 4], PencairanPengajuan::orderBy('nomor_urut')->pluck('nomor_urut')->all());
            $this->assertSame(4, PencairanPengajuan::distinct()->count('nomor_pengajuan'));
            $this->assertDatabaseCount('pengajuan_revisions', 4); $this->assertDatabaseCount('pengajuan_histories', 4);
            $this->assertDatabaseCount('pengajuan_idempotencies', 4);
            $this->assertSame(4, DB::table('pengajuan_number_sequences')->value('last_number'));
        } finally {
            foreach ($processes as [$proc, $pipes]) {
                if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            }
            DB::disconnect('sqlite'); config(['database.connections.sqlite.database' => $original]); DB::purge('sqlite');
            if (is_file($path)) { unlink($path); }
        }
    }
}