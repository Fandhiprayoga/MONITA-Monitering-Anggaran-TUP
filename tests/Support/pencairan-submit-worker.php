<?php

// Isolated subprocess for the file-based SQLite concurrency integration test only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['app.env' => 'testing', 'database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'pencairan.actor_source_no_stable' => false]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 10000');
Illuminate\Support\Carbon::setTestNow('2026-10-31 10:00:00');
$sheets = new class extends App\Services\GoogleSheetService {
    public function __construct() {}
    public function getPencairanDrkOptions(string $pp, int $tw, int $year): array
    {
        return [['id' => str_repeat('a', 64), 'kode_pp' => $pp, 'saldo_awal' => '1000', 'anggaran_terpakai' => '0', 'saldo_terkini' => '1000']];
    }
};
$p = App\Models\PencairanPengajuan::findOrFail($argv[2]);
$session = new Illuminate\Session\Store('test', new Illuminate\Session\ArraySessionHandler(120));
$session->start();
$session->put(['user_authenticated' => true, 'user_role' => 'user', 'user_data' => ['username' => 'parallel-'.$p->kode_pp, 'role' => 'user', 'kode_pp' => $p->kode_pp, 'nama_pp' => $p->nama_pp]]);
$request = Illuminate\Http\Request::create('/submit', 'POST', ['lock_version' => 0, 'idempotency_key' => 'parallel-'.$p->id]);
$request->setLaravelSession($session);
try {
    echo json_encode((new App\Services\PencairanService($sheets))->submit($request, $p), JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()); exit(1);
}