<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use App\Services\PencairanSlaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanSlaTest extends TestCase
{
    use RefreshDatabase;

    private PencairanActor $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PencairanActor::create([
            'source_key' => 'sla-admin', 'username' => 'admin', 'name' => 'Admin', 'role' => 'admin',
        ]);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function pengajuan(string $jenis, string $status, array $timestamps = []): PencairanPengajuan
    {
        return PencairanPengajuan::create(array_merge([
            'jenis' => $jenis, 'status' => $status, 'kode_pp' => 'SIF', 'nama_pp' => 'Unit SIF',
            'created_by_actor_id' => $this->admin->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
            'nomor_pengajuan' => strtoupper($jenis).'-'.strtoupper($status).'-'.uniqid(),
            'uraian_pengajuan' => 'Pembayaran kegiatan',
        ], $timestamps));
    }

    public function test_business_day_targets_and_weekend_rollover_are_calculated_for_each_stage(): void
    {
        $service = new PencairanSlaService;
        $now = CarbonImmutable::parse('2026-11-17 10:00:00');
        $verify = $service->measure($this->pengajuan('beban', 'submitted', [
            'last_submitted_at' => '2026-11-16 09:00:00',
        ]), $now);
        $beban = $service->measure($this->pengajuan('beban', 'verified', [
            'verified_at' => '2026-11-16 09:00:00',
        ]), $now);
        $imprestfund = $service->measure($this->pengajuan('imprestfund', 'verified', [
            'verified_at' => '2026-11-16 09:00:00',
        ]), $now);

        $this->assertSame('2026-11-18', $verify['due_date']->toDateString());
        $this->assertSame('mendekati', $verify['status']);
        $this->assertSame(2, $verify['target_days']);
        $this->assertSame('2026-11-23', $beban['due_date']->toDateString());
        $this->assertSame(5, $beban['target_days']);
        $this->assertSame('2026-11-18', $imprestfund['due_date']->toDateString());
        $this->assertSame(2, $imprestfund['target_days']);

        $weekend = $service->measure($this->pengajuan('imprestfund', 'verified', [
            'verified_at' => '2026-11-20 09:00:00',
        ]), CarbonImmutable::parse('2026-11-23 09:00:00'));
        $this->assertSame('2026-11-24', $weekend['due_date']->toDateString());

        $pastDueWeekend = $service->measure($this->pengajuan('imprestfund', 'verified', [
            'verified_at' => '2026-11-11 09:00:00',
        ]), CarbonImmutable::parse('2026-11-14 09:00:00'));
        $this->assertSame('terlambat', $pastDueWeekend['status']);
        $this->assertSame(0, $pastDueWeekend['overdue_days']);
    }

    public function test_monitoring_filters_current_work_and_shows_sla_status_and_detail_link(): void
    {
        CarbonImmutable::setTestNow('2026-11-17 10:00:00');
        $submitted = $this->pengajuan('honor', 'submitted', [
            'last_submitted_at' => '2026-11-16 09:00:00',
        ]);
        $verified = $this->pengajuan('imprestfund', 'verified', [
            'verified_at' => '2026-11-12 09:00:00',
        ]);
        $this->pengajuan('beban', 'returned', ['last_submitted_at' => '2026-11-10 09:00:00']);
        $this->get(route('pencairan.sla'))
            ->assertOk()
            ->assertViewIs('main.pencairan.sla')
            ->assertSee('ti ti-clock')
            ->assertSee('data-bs-target="#modalFilterSla"', false)
            ->assertSee('data-bs-target="#modalKeteranganSla"', false)
            ->assertSee('aria-label="Bantuan keterangan SLA"', false)
            ->assertSee('Filter monitoring SLA')
            ->assertSee('Keterangan Monitoring SLA')
            ->assertSee('maksimal 5 hari kerja sejak diverifikasi.')
            ->assertSee('class="pc-link active"', false)
            ->assertSee('href="'.route('pencairan.sla').'"', false)
            ->assertSee('Monitoring SLA Pencairan')
            ->assertSee($submitted->nomor_pengajuan)
            ->assertSee($verified->nomor_pengajuan)
            ->assertSee('Terlambat')
            ->assertSee(route('pencairan.show', ['pengajuan' => $submitted, 'menu' => 'sla']))
            ->assertDontSee('BEBAN-RETURNED');
        $this->get(route('pencairan.show', ['pengajuan' => $submitted, 'menu' => 'sla']))
            ->assertOk()
            ->assertViewHas('activeMenu', 'sla')
            ->assertViewHas('backUrl', route('pencairan.sla'));

        $response = $this->get(route('pencairan.sla', ['tahap' => 'transaksi', 'jenis' => 'imprestfund']))
            ->assertOk()
            ->assertSee('Filter aktif:')
            ->assertSee('Tahap: Transaksi')
            ->assertSee('Jenis: Imprestfund');
        $this->assertSame([$verified->id], $response->viewData('items')->getCollection()->pluck('id')->all());
        $this->assertSame(1, $response->viewData('summary')['terlambat']);
    }

    public function test_monitoring_is_restricted_to_admin_and_unavailable_to_unit_navigation(): void
    {
        $this->withSession([
            'user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF'],
        ]);
        $this->get(route('pencairan.sla'))->assertForbidden();
    }
}
