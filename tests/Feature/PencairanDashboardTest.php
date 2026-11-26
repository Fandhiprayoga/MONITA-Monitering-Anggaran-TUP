<?php

namespace Tests\Feature;

use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PencairanDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $drk = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 25));
        DB::table('pengajuan_actors')->insert([
            'id' => 1, 'source_key' => 'actor', 'username' => 'user', 'name' => 'User', 'role' => 'user',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->options = [
            'id' => $this->drk, 'drk_tup' => 'DRK-1', 'kode_akun' => '001',
            'tipe' => 'BANG', 'uraian_drk' => 'Kegiatan', 'saldo_awal' => '1000,00',
        ];
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getPencairanDrkOptions')->andReturnUsing(
                fn ($unit, $tw, $year) => [$this->options]
            );
            $mock->shouldReceive('getPencairanUnitsFromSheet')->andReturn(['PP01' => 'Unit Satu', 'PP02' => 'Unit Dua']);
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
        });
        $this->login();
    }

    private function login(string $role = 'user'): void
    {
        $this->withSession([
            'user_authenticated' => true, 'user_role' => $role,
            'user_data' => ['username' => 'user', 'role' => $role, 'kode_pp' => 'PP01', 'nama_pp' => 'Unit Satu'],
        ]);
    }

    private function pengajuan(string $status, string $nominal, int $quarter = 4, string $unit = 'PP01', ?string $drk = null): void
    {
        PencairanPengajuan::create([
            'jenis' => 'beban', 'status' => $status, 'kode_pp' => $unit, 'nama_pp' => 'Unit',
            'created_by_actor_id' => 1, 'tahun_anggaran' => 2026, 'triwulan' => $quarter,
            'drk_source_id' => $drk ?? $this->drk, 'total_bruto' => $nominal,
        ]);
    }

    public function test_quarter_calculates_cards_counts_and_scopes_all_records_to_unit(): void
    {
        foreach (['completed' => '100.00', 'submitted' => '200.00', 'verified' => '50.00',
            'returned' => '75.00', 'draft' => '999.00', 'cancelled' => '999.00'] as $status => $amount) {
            $this->pengajuan($status, $amount);
        }
        $this->pengajuan('completed', '400.00', 4, 'PP02');
        $this->pengajuan('completed', '500.00', 3);

        $response = $this->get(route('pencairan.dashboard', ['tahun' => 2026, 'tw' => 4]));
        $response->assertOk()->assertSee('Dashboard Pencairan Anggaran')->assertSee('DRK-1');
        $data = $response->viewData('data');
        $this->assertSame('1000.00', (string) $data['totals']['pagu']->toScale(2));
        $this->assertSame('100.00', (string) $data['totals']['realisasi']->toScale(2));
        $this->assertSame('250.00', (string) $data['totals']['proses']->toScale(2));
        $this->assertSame('650.00', (string) $data['totals']['saldo']->toScale(2));
        $this->assertSame(['total' => 4, 'completed' => 1, 'process' => 2, 'returned' => 1], $data['counts']);
        $this->assertSame('10.0000', $data['chart']['realisasi']);
        $this->assertSame('25.0000', $data['chart']['proses']);
        $this->assertSame('65.0000', $data['chart']['saldo']);
        $this->assertSame(['pagu' => '100,00', 'realisasi' => '10,00', 'proses' => '25,00', 'saldo' => '65,00'], $data['percentages']);
        $response->assertSee('100,00% dari Pagu Anggaran')
            ->assertSee('10,00% dari Pagu Anggaran')
            ->assertSee('25,00% dari Pagu Anggaran')
            ->assertSee('65,00% dari Pagu Anggaran');
        $this->assertMatchesRegularExpression('/<tfoot\b[^>]*>\s*<tr>\s*<th scope="row" colspan="4" class="text-center">Total<\/th>\s*<td[^>]*>Rp 1\.000,00<\/td>\s*<td[^>]*>Rp 100,00<\/td>\s*<td[^>]*>Rp 250,00<\/td>\s*<td[^>]*>Rp 650,00<\/td>\s*<\/tr>\s*<\/tfoot>/', $response->getContent());
    }

    public function test_annual_sums_four_quarters_and_merges_same_drk(): void
    {
        $this->pengajuan('completed', '100.00', 1);
        $this->pengajuan('verified', '50.00', 4);
        $response = $this->get(route('pencairan.dashboard', ['tahun' => 2026, 'tw' => 'tahunan']))->assertOk();
        $response->assertSee('table table-striped table-bordered align-middle mb-0')
            ->assertSee('bg-secondary text-center')
            ->assertSee('<th class="text-white" scope="col">Saldo Awal</th>', false);
        $data = $response->viewData('data');
        $this->assertCount(1, $data['rows']);
        $this->assertSame('4000.00', (string) $data['totals']['pagu']->toScale(2));
        $this->assertSame('3850.00', (string) $data['rows'][0]['saldo']->toScale(2));
        $this->assertSame(2, $data['counts']['total']);
        $response->assertSee('2,50% dari Pagu Anggaran')
            ->assertSee('1,25% dari Pagu Anggaran')
            ->assertSee('96,25% dari Pagu Anggaran');
        $this->assertMatchesRegularExpression('/<tfoot\b[^>]*>.*?Rp 4\.000,00.*?Rp 100,00.*?Rp 50,00.*?Rp 3\.850,00.*?<\/tfoot>/s', $response->getContent());
    }

    public function test_user_cannot_choose_another_unit_or_unavailable_year(): void
    {
        $this->get(route('pencairan.dashboard', ['unit' => 'PP02']))->assertForbidden();
        $this->get(route('pencairan.dashboard', ['tahun' => 2025]))->assertSessionHasErrors('tahun');
    }

    public function test_admin_can_select_unit_and_only_sees_its_records(): void
    {
        $this->pengajuan('completed', '100.00');
        $this->pengajuan('completed', '400.00', 4, 'PP02');
        $this->login('admin');
        $data = $this->get(route('pencairan.dashboard', ['unit' => 'PP02', 'tw' => 4]))
            ->assertOk()->viewData('data');
        $this->assertSame('400.00', (string) $data['totals']['realisasi']->toScale(2));
        $this->assertSame(1, $data['counts']['total']);
    }

    public function test_missing_source_or_unmatched_drk_never_produces_false_totals(): void
    {
        $this->options['saldo_awal'] = null;
        $this->get(route('pencairan.dashboard'))->assertStatus(503)->assertSee('Saldo awal DRK pada TW4 tidak tersedia atau tidak valid');
        $this->options['saldo_awal'] = '1000,00';
        $this->pengajuan('submitted', '100.00', 4, 'PP01', str_repeat('b', 64));
        $this->get(route('pencairan.dashboard'))->assertStatus(503)->assertSee('DRK pengajuan pada TW4 tidak ditemukan');
    }

    public function test_negative_balance_is_visible_but_chart_is_not_misleading(): void
    {
        $this->pengajuan('completed', '1200.00');
        $response = $this->get(route('pencairan.dashboard'))->assertOk()
            ->assertSee('-Rp 200,00')->assertSee('Grafik tidak dapat ditampilkan')
            ->assertSee('120,00% dari Pagu Anggaran')->assertSee('-20,00% dari Pagu Anggaran');
        $this->assertMatchesRegularExpression('/<tfoot\b[^>]*>.*?-Rp 200,00.*?<\/tfoot>/s', $response->getContent());
        $this->assertNull($response->viewData('data')['chart']);
    }

    public function test_percentages_are_rounded_and_unavailable_for_nonpositive_budget(): void
    {
        $this->options['saldo_awal'] = '300,00';
        $this->pengajuan('completed', '100.00');
        $this->get(route('pencairan.dashboard'))->assertOk()
            ->assertSee('33,33% dari Pagu Anggaran')
            ->assertSee('66,67% dari Pagu Anggaran');

        foreach (['0,00', '-100,00'] as $opening) {
            $this->options['saldo_awal'] = $opening;
            $response = $this->get(route('pencairan.dashboard'))->assertOk()
                ->assertSee('Persentase tidak tersedia (pagu nol atau negatif)')
                ->assertDontSee('% dari Pagu Anggaran');
            $this->assertNull($response->viewData('data')['percentages']);
        }
    }

    public function test_duplicate_source_drk_and_source_failure_do_not_show_partial_totals(): void
    {
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getPencairanDrkOptions')->andReturnUsing(fn () => [$this->options, $this->options]);
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
        });
        $this->get(route('pencairan.dashboard'))->assertStatus(503)->assertDontSee('Pagu Anggaran');

        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getPencairanDrkOptions')->andThrow(new \RuntimeException('private upstream error'));
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
        });
        $this->get(route('pencairan.dashboard'))->assertStatus(503)->assertDontSee('private upstream error');
    }

    public function test_available_historical_years_are_scoped_to_the_actor_and_current_year_is_available(): void
    {
        $this->pengajuan('completed', '100.00', 4, 'PP02');
        PencairanPengajuan::where('kode_pp', 'PP02')->update(['tahun_anggaran' => 2025]);
        $response = $this->get(route('pencairan.dashboard'))->assertOk();
        $this->assertSame([2026], $response->viewData('years'));
        $this->pengajuan('returned', '100.00');
        PencairanPengajuan::where('kode_pp', 'PP01')->update(['tahun_anggaran' => 2024]);
        $response = $this->get(route('pencairan.dashboard', ['tahun' => 2024, 'tw' => 4]))->assertOk();
        $this->assertSame([2026, 2024], $response->viewData('years'));
        $this->assertSame(1, $response->viewData('data')['counts']['returned']);
    }

    public function test_dummy_records_are_excluded_from_all_dashboard_totals_and_years_without_being_deleted(): void
    {
        $this->pengajuan('completed', '100.00');
        $this->pengajuan('verified', '50.00');
        PencairanPengajuan::where('status', 'verified')->update(['header_snapshot' => ['dummy' => false]]);
        foreach (['completed', 'submitted', 'verified', 'returned'] as $status) {
            $this->pengajuan($status, '999.00', 4, 'PP01', str_repeat('b', 64));
            PencairanPengajuan::latest('id')->first()->update(['header_snapshot' => ['dummy' => true]]);
        }
        $this->pengajuan('completed', '999.00', 4, 'PP01', str_repeat('c', 64));
        PencairanPengajuan::latest('id')->first()->update(['tahun_anggaran' => 2025, 'header_snapshot' => ['dummy' => true]]);

        foreach (['4', 'tahunan'] as $period) {
            $response = $this->get(route('pencairan.dashboard', ['tw' => $period]))->assertOk()
                ->assertSee('Pengajuan yang ditandai dummy tidak termasuk perhitungan dashboard.');
            $data = $response->viewData('data');
            $this->assertSame([2026], $response->viewData('years'));
            $this->assertSame(['total' => 2, 'completed' => 1, 'process' => 1, 'returned' => 0], $data['counts']);
            $this->assertSame('100.00', (string) $data['totals']['realisasi']->toScale(2));
            $this->assertSame('50.00', (string) $data['totals']['proses']->toScale(2));
        }
        $this->assertSame(7, PencairanPengajuan::count());
    }
}
