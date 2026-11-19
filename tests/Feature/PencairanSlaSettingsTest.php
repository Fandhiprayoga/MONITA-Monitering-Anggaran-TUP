<?php

namespace Tests\Feature;

use App\Services\PencairanSlaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanSlaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-19 09:00:00', config('app.timezone')));
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'no' => '1'],
        ]);
    }

    private function schedule(): array
    {
        return [
            'effective_from' => '2026-11-20',
            'verifikasi' => 3, 'transaksi_imprestfund' => 4, 'transaksi_beban' => 6, 'transaksi_honor' => 7,
        ];
    }

    public function test_admin_can_schedule_and_view_all_four_targets_under_settings(): void
    {
        $this->get(route('settings.sla.index'))->assertOk()
            ->assertSeeInOrder(['Settings', 'Pengaturan', 'Target SLA', 'Target SLA Pencairan'])
            ->assertSee('Riwayat Target SLA')
            ->assertViewHas('activeTargets', PencairanSlaService::INITIAL_TARGETS);
        $this->post(route('settings.sla.store'), $this->schedule() + ['created_by' => 'other'])
            ->assertRedirect(route('settings.sla.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('pencairan_sla_policies', $this->schedule() + ['created_by' => 'admin']);
        $this->get(route('settings.sla.index'))->assertOk()->assertSee('Terjadwal')
            ->assertViewHas('activeTargets', PencairanSlaService::INITIAL_TARGETS);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 00:00:00', config('app.timezone')));
        $this->get(route('settings.sla.index'))->assertOk()
            ->assertViewHas('activeTargets', [
                'verifikasi' => 3, 'transaksi_imprestfund' => 4, 'transaksi_beban' => 6, 'transaksi_honor' => 7,
            ]);
    }

    public function test_schedule_rejects_retroactive_duplicate_and_invalid_values(): void
    {
        foreach ([
            ['effective_from' => '2026-11-19'], ['effective_from' => '2026-11-18'],
            ['effective_from' => '2026-02-31'], ['verifikasi' => 0],
            ['transaksi_imprestfund' => 366], ['transaksi_beban' => 1.5], ['transaksi_honor' => null],
        ] as $invalid) {
            $this->postJson(route('settings.sla.store'), $invalid + $this->schedule())
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertDatabaseEmpty('pencairan_sla_policies');
        $this->post(route('settings.sla.store'), $this->schedule())->assertRedirect(route('settings.sla.index'));
        $this->postJson(route('settings.sla.store'), ['verifikasi' => 99] + $this->schedule())
            ->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->assertDatabaseCount('pencairan_sla_policies', 1);
        $this->assertDatabaseHas('pencairan_sla_policies', $this->schedule());
    }

    public function test_unit_user_cannot_open_or_save_settings(): void
    {
        $this->withSession([
            'user_role' => 'user',
            'user_data' => ['username' => 'unit', 'role' => 'user', 'kode_pp' => 'SIF'],
        ]);
        $this->get(route('settings.sla.index'))->assertForbidden();
        $this->post(route('settings.sla.store'), $this->schedule())->assertForbidden();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee(route('settings.sla.index'));
        $this->assertDatabaseEmpty('pencairan_sla_policies');
    }
}
