<?php

namespace Tests\Feature;

use App\Models\JenisPenghasilan;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class JenisPenghasilanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GoogleSheetService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getUnitsFromSheet')->andReturn(['TEST' => 'Unit Test']);
        });
        $this->withSession(['user_authenticated' => true, 'user_role' => 'admin']);
    }

    public function test_admin_can_create_update_and_delete_income_types(): void
    {
        $this->get(route('jenis-penghasilan.index'))
            ->assertOk()
            ->assertSee('Jenis Penghasilan')
            ->assertSee('Tarif PPh (%)');

        $this->post(route('jenis-penghasilan.store'), [
            'kode_jenis' => ' 01 ',
            'nama_jenis_pph' => 'Honorarium',
            'tarif_pph' => '2.50',
        ])->assertRedirect(route('jenis-penghasilan.index'));

        $jenisPenghasilan = JenisPenghasilan::firstOrFail();
        $this->assertSame('01', $jenisPenghasilan->kode_jenis);
        $this->assertSame('2.50', $jenisPenghasilan->tarif_pph);

        $this->patch(route('jenis-penghasilan.update', $jenisPenghasilan), [
            'kode_jenis' => '02',
            'nama_jenis_pph' => 'Jasa',
            'tarif_pph' => '5',
        ])->assertRedirect(route('jenis-penghasilan.index'));

        $this->assertDatabaseHas('jenis_penghasilan', [
            'id' => $jenisPenghasilan->id,
            'kode_jenis' => '02',
            'nama_jenis_pph' => 'Jasa',
            'tarif_pph' => '5.00',
        ]);

        $this->delete(route('jenis-penghasilan.destroy', $jenisPenghasilan))
            ->assertRedirect(route('jenis-penghasilan.index'));
        $this->assertDatabaseCount('jenis_penghasilan', 0);
    }

    public function test_code_must_be_unique_and_tax_rate_must_be_a_percentage(): void
    {
        JenisPenghasilan::create([
            'kode_jenis' => 'ABC',
            'nama_jenis_pph' => 'Honorarium',
            'tarif_pph' => '2.50',
        ]);

        $this->from(route('jenis-penghasilan.index'))
            ->post(route('jenis-penghasilan.store'), [
                'kode_jenis' => 'abc',
                'nama_jenis_pph' => 'Duplikat',
                'tarif_pph' => '100.01',
            ])
            ->assertRedirect(route('jenis-penghasilan.index'))
            ->assertSessionHasErrors(['kode_jenis', 'tarif_pph']);

        $this->assertDatabaseCount('jenis_penghasilan', 1);
    }

    public function test_non_admin_cannot_manage_income_types(): void
    {
        $this->withSession(['user_role' => 'user'])
            ->get(route('jenis-penghasilan.index'))
            ->assertForbidden();

        $this->withSession(['user_role' => 'user'])
            ->post(route('jenis-penghasilan.store'), [
                'kode_jenis' => '01',
                'nama_jenis_pph' => 'Honorarium',
                'tarif_pph' => '2.50',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('jenis_penghasilan', 0);
    }
}
