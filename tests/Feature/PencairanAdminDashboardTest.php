<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private PencairanActor $admin;
    private string $drk = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 25));
        $this->admin = PencairanActor::create([
            'source_key' => 'admin', 'username' => 'admin', 'name' => 'Admin', 'role' => 'admin',
        ]);
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
            $mock->shouldReceive('getPencairanUnitsFromSheet')->andReturn(['PP01' => 'Unit Satu', 'PP02' => 'Unit Dua']);
            $mock->shouldReceive('getPencairanDrkOptions')->andReturn([[
                'id' => $this->drk, 'drk_tup' => 'DRK-1', 'kode_akun' => '001',
                'tipe' => 'BANG', 'uraian_drk' => 'Kegiatan', 'saldo_awal' => '1000,00',
            ]]);
            $mock->shouldReceive('getPencairanDrkOptionsForUnits')->andReturnUsing(function ($units, $quarter, $year) {
                $options = [];
                foreach ($units as $unit) {
                    $options[$unit] = [[
                        'id' => $this->drk, 'drk_tup' => 'DRK-1', 'kode_akun' => '001',
                        'tipe' => 'BANG', 'uraian_drk' => 'Kegiatan', 'saldo_awal' => '1000,00',
                    ]];
                }
                return $options;
            });
        });
        $this->login();
    }

    private function login(string $role = 'admin'): void
    {
        $this->withSession([
            'user_authenticated' => true, 'user_role' => $role,
            'user_data' => ['username' => 'admin', 'role' => $role, 'kode_pp' => 'PP01', 'nama_pp' => 'Unit Satu'],
        ]);
    }

    private function pengajuan(string $status, string $jenis = 'beban', string $unit = 'PP01', int $tw = 4, array $extra = []): PencairanPengajuan
    {
        return PencairanPengajuan::create($extra + [
            'jenis' => $jenis, 'status' => $status, 'kode_pp' => $unit, 'nama_pp' => $unit,
            'created_by_actor_id' => $this->admin->id, 'tahun_anggaran' => 2026, 'triwulan' => $tw,
            'drk_source_id' => $this->drk, 'total_bruto' => '100.00',
            'first_submitted_at' => $status === 'draft' ? null : '2026-11-12 08:00:00',
        ]);
    }

    private function event(PencairanPengajuan $pengajuan, string $action, string $date, int $revision = 1): void
    {
        $pengajuan->histories()->create([
            'action' => $action, 'revision_no' => $revision, 'to_status' => 'submitted',
            'actor_id' => $this->admin->id, 'actor_name' => 'Admin', 'actor_role' => 'admin',
            'created_at' => $date,
        ]);
    }

    public function test_admin_counts_and_all_units_budget_are_consistent(): void
    {
        $this->pengajuan('submitted');
        $this->pengajuan('returned', 'honor');
        $this->pengajuan('verified', 'imprestfund');
        $this->pengajuan('cancelled', 'beban', 'PP02');
        $this->pengajuan('draft');
        $this->pengajuan('completed', 'beban', 'PP01', 3);
        $this->pengajuan('completed', 'honor', 'PP01', 4, ['header_snapshot' => ['dummy' => true]]);

        $response = $this->get(route('pencairan.dashboard-admin', ['tahun' => 2026, 'tw' => 4]))->assertOk()
            ->assertSee('Dashboard Pencairan Admin')->assertSee('Unit Dua')->assertSee('Belum ada data')
            ->assertSee(route('pencairan.dashboard-admin'));
        $this->assertSame([
            'masuk' => 4, 'returned' => 1, 'submitted' => 1,
            'imprestfund_queue' => 1, 'beban_queue' => 0, 'honor_queue' => 0,
            'total' => 4, 'cancelled' => 1, 'imprestfund' => 1, 'beban' => 1, 'honor' => 1,
        ], $response->viewData('statistics')['counts']);
        $budget = $response->viewData('budget');
        $this->assertCount(2, $budget['rows']);
        $this->assertSame('2000.00', (string) $budget['totals']['pagu']->toScale(2));
        $this->assertSame('200.00', (string) $budget['totals']['proses']->toScale(2));
        $this->assertSame('1800.00', (string) $budget['totals']['saldo']->toScale(2));

        $filtered = $this->get(route('pencairan.dashboard-admin', ['unit' => 'PP02', 'tw' => 4]))->assertOk();
        $this->assertSame(1, $filtered->viewData('statistics')['counts']['total']);
        $this->assertCount(1, $filtered->viewData('budget')['rows']);
    }

    public function test_sla_uses_latest_completed_stage_and_excludes_missing_samples(): void
    {
        $p = $this->pengajuan('completed', 'imprestfund');
        $this->event($p, 'submit', '2026-11-12 08:00:00');
        $this->event($p, 'verify', '2026-11-16 08:00:00');
        $this->event($p, 'complete', '2026-11-19 08:00:00');
        $this->pengajuan('completed', 'imprestfund');
        $this->pengajuan('submitted', 'honor');
        $stages = $this->get(route('pencairan.dashboard-admin', ['tw' => 4]))->assertOk()->viewData('statistics')['stages'];
        $this->assertSame(1, $stages['verifikasi']['tepat_waktu']);
        $this->assertSame(1, $stages['verifikasi']['data_missing']);
        $this->assertSame(100.0, $stages['verifikasi']['percent']);
        $this->assertSame(2.0, $stages['verifikasi']['average']);
        $this->assertSame(1, $stages['transaksi_imprestfund']['terlambat']);
        $this->assertSame(1, $stages['transaksi_imprestfund']['data_missing']);
        $this->assertSame(0.0, $stages['transaksi_imprestfund']['percent']);
        $this->assertSame(3.0, $stages['transaksi_imprestfund']['average']);
        $this->assertNull($stages['transaksi_honor']['percent']);
    }

    public function test_annual_budget_sums_quarters_and_filters_are_validated(): void
    {
        $this->pengajuan('completed', 'beban', 'PP01', 1);
        $response = $this->get(route('pencairan.dashboard-admin', ['unit' => 'PP01', 'tahun' => 2026, 'tw' => 'tahunan']))->assertOk();
        $this->assertSame('4000.00', (string) $response->viewData('budget')['totals']['pagu']->toScale(2));
        $this->assertSame('3900.00', (string) $response->viewData('budget')['totals']['saldo']->toScale(2));
        $this->get(route('pencairan.dashboard-admin', ['unit' => 'UNKNOWN']))->assertStatus(422);
        $this->get(route('pencairan.dashboard-admin', ['tw' => '5']))->assertSessionHasErrors('tw');
    }

    public function test_resubmitted_pengajuan_is_counted_once_and_uses_latest_finished_verification(): void
    {
        $p = $this->pengajuan('returned', 'beban');
        $this->event($p, 'submit', '2026-11-02 08:00:00');
        $this->event($p, 'verify', '2026-11-06 08:00:00');
        $this->event($p, 'return', '2026-11-06 09:00:00');
        $this->event($p, 'submit', '2026-11-09 08:00:00', 2);
        $this->event($p, 'verify', '2026-11-10 08:00:00', 2);
        $this->event($p, 'return', '2026-11-10 09:00:00', 2);
        $stats = $this->get(route('pencairan.dashboard-admin'))->assertOk()->viewData('statistics');
        $this->assertSame(1, $stats['counts']['masuk']);
        $this->assertSame(1, $stats['counts']['returned']);
        $this->assertSame(1, $stats['stages']['verifikasi']['tepat_waktu']);
        $this->assertSame(0, $stats['stages']['verifikasi']['terlambat']);
        $this->assertSame(1.0, $stats['stages']['verifikasi']['average']);
    }

    public function test_sheet_failure_and_unknown_units_never_expose_partial_budget(): void
    {
        $this->pengajuan('submitted', 'beban', 'UNKNOWN');
        $response = $this->get(route('pencairan.dashboard-admin'))->assertStatus(503)
            ->assertSee('tidak tersedia dalam daftar spreadsheet');
        $this->assertNull($response->viewData('budget'));
        PencairanPengajuan::query()->delete();
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
            $mock->shouldReceive('getPencairanUnitsFromSheet')->andReturn(['PP01' => 'Unit Satu', 'PP02' => 'Unit Dua']);
            $mock->shouldReceive('getPencairanDrkOptionsForUnits')->andThrow(new \RuntimeException('private upstream error'));
        });
        $response = $this->get(route('pencairan.dashboard-admin'))->assertStatus(503)
            ->assertDontSee('private upstream error')->assertSee('Total dan rincian anggaran tidak ditampilkan');
        $this->assertNull($response->viewData('budget'));
    }

    public function test_non_admin_cannot_access_dashboard_or_see_sidebar_entry(): void
    {
        $this->login('user');
        $this->get(route('pencairan.dashboard-admin'))->assertForbidden();
        $this->get(route('pencairan.dashboard'))->assertOk()->assertDontSee('Dashboard Pencairan Admin');
    }
}
