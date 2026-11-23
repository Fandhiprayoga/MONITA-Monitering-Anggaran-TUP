<?php

namespace Tests\Feature;

use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanSubmissionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
            $mock->shouldReceive('getPencairanUnitsFromSheet')->andReturn(['PP01' => 'Unit']);
            $mock->shouldReceive('getPencairanDrkOptions')->andReturn([]);
        });
        $this->login('admin', 'admin1');
    }

    private function login(string $role, string $username): void
    {
        $this->withSession([
            'user_authenticated' => true, 'user_role' => $role,
            'user_data' => ['username' => $username, 'role' => $role, 'kode_pp' => 'PP01', 'nama_pp' => 'Unit'],
        ]);
    }

    public function test_admin_changes_status_with_history_and_no_duplicate_history(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(16, 5, 0));
        $this->get(route('settings.pencairan.index'))->assertOk()
            ->assertSee('Pengaturan Pengajuan Pencairan')->assertSee('Status awal: Buka');
        $this->post(route('settings.pencairan.update'), ['status' => 'closed', 'actor_username' => 'forged'])
            ->assertRedirect(route('settings.pencairan.index'));
        $this->assertDatabaseHas('pencairan_submission_setting_histories', [
            'from_open' => true, 'to_open' => false, 'actor_username' => 'admin1',
            'created_at' => '2026-11-23 16:05:00',
        ]);
        $this->post(route('settings.pencairan.update'), ['status' => 'closed'])->assertSessionHas('success', 'Status pengajuan tidak berubah.');
        $this->assertDatabaseCount('pencairan_submission_setting_histories', 1);
        $this->login('admin', 'admin2');
        $this->post(route('settings.pencairan.update'), ['status' => 'open'])->assertRedirect();
        $this->assertDatabaseHas('pencairan_submission_setting_histories', [
            'from_open' => false, 'to_open' => true, 'actor_username' => 'admin2',
        ]);
        $this->get(route('settings.pencairan.index'))->assertOk()->assertSeeInOrder(['admin2', 'admin1']);
        $this->postJson(route('settings.pencairan.update'), ['status' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('pencairan_submission_setting_histories', 2);
    }

    public function test_closed_status_is_visible_and_restricts_user_forms_but_not_admin_forms(): void
    {
        $this->post(route('settings.pencairan.update'), ['status' => 'closed'])->assertRedirect();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee('Admin tetap dapat membuat dan mengirim pengajuan.')
            ->assertDontSee('data-bs-target="#modalPilihForm" disabled', false);
        foreach (['imprestfund', 'beban', 'honor'] as $kind) {
            $this->get(route('pencairan.form', $kind))->assertOk()->assertSee('Submit Pengajuan');
        }
        $this->login('user', 'unit1');
        $this->get(route('settings.pencairan.index'))->assertForbidden();
        $this->post(route('settings.pencairan.update'), ['status' => 'open'])->assertForbidden();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee('Pengajuan sedang ditutup')
            ->assertSee('data-bs-target="#modalPilihForm" disabled', false)
            ->assertDontSee(route('settings.pencairan.index'));
        foreach (['imprestfund', 'beban', 'honor'] as $kind) {
            $this->get(route('pencairan.form', $kind))->assertForbidden()->assertSee('Pengajuan pencairan anggaran sedang ditutup');
            $this->postJson(route('pencairan.store'), ['jenis' => $kind])->assertForbidden()
                ->assertJsonPath('message', 'Pengajuan pencairan anggaran sedang ditutup. Silakan hubungi admin untuk informasi lebih lanjut.');
        }
        $this->login('admin', 'admin1');
        $this->post(route('settings.pencairan.update'), ['status' => 'open'])->assertRedirect();
        $this->login('user', 'unit1');
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee('Pengajuan sedang ditutup');
        $this->get(route('pencairan.form', 'beban'))->assertOk();
    }
}
