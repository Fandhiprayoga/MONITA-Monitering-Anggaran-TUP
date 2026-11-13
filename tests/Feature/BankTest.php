<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Services\GoogleSheetService;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BankTest extends TestCase
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

    public function test_admin_can_create_update_and_change_bank_status(): void
    {
        $this->get(route('bank.index'))
            ->assertOk()
            ->assertSee('Master Data Bank')
            ->assertSee('Kode Bank')
            ->assertSee('Inisial')
            ->assertSee('Nama Bank')
            ->assertSee('Status');

        $this->get(route('bank.create'))
            ->assertOk()
            ->assertSee('Tambah Bank');

        $this->post(route('bank.store'), [
            'kode_bank' => ' bni ',
            'inisial' => 'bni',
            'nama_bank' => 'Bank Negara Indonesia',
            'status' => 'aktif',
        ])->assertRedirect(route('bank.index'));

        $bank = Bank::firstOrFail();
        $this->assertSame('BNI', $bank->kode_bank);
        $this->assertSame('BNI', $bank->inisial);
        $this->assertSame('aktif', $bank->status);

        $this->get(route('bank.edit', $bank))
            ->assertOk()
            ->assertSee('Ubah Bank')
            ->assertSee('Bank Negara Indonesia');

        $this->patch(route('bank.update', $bank), [
            'kode_bank' => 'BNI',
            'inisial' => 'BNI',
            'nama_bank' => 'BNI',
            'status' => 'nonaktif',
        ])->assertRedirect(route('bank.index'));

        $this->assertDatabaseHas('banks', [
            'id' => $bank->id,
            'kode_bank' => 'BNI',
            'inisial' => 'BNI',
            'nama_bank' => 'BNI',
            'status' => 'nonaktif',
        ]);
    }

    public function test_bank_code_must_be_unique_and_status_must_be_valid(): void
    {
        Bank::create(['kode_bank' => 'BNI', 'inisial' => 'BNI', 'nama_bank' => 'BNI', 'status' => 'aktif']);

        $this->from(route('bank.create'))
            ->post(route('bank.store'), [
                'kode_bank' => 'bni',
                'inisial' => 'bni',
                'nama_bank' => 'Duplikat',
                'status' => 'invalid',
            ])
            ->assertRedirect(route('bank.create'))
            ->assertSessionHasErrors(['kode_bank', 'inisial', 'status']);

        $this->assertDatabaseCount('banks', 1);
    }

    public function test_bank_seeder_inserts_all_banks_without_overwriting_existing_status(): void
    {
        $existingBank = Bank::create([
            'kode_bank' => '002',
            'inisial' => 'BRI',
            'nama_bank' => 'Bank Rakyat Indonesia',
            'status' => 'nonaktif',
        ]);

        $this->seed(BankSeeder::class);
        $this->seed(BankSeeder::class);

        $this->assertDatabaseCount('banks', 33);
        $this->assertDatabaseHas('banks', [
            'id' => $existingBank->id,
            'kode_bank' => '002',
            'inisial' => 'BRI',
            'nama_bank' => 'Bank Rakyat Indonesia',
            'status' => 'nonaktif',
        ]);
        $this->assertDatabaseHas('banks', [
            'kode_bank' => '111',
            'inisial' => 'DKI / JAKARTA',
            'nama_bank' => 'Bank DKI / Bank Jakarta',
            'status' => 'aktif',
        ]);
    }

    public function test_non_admin_cannot_manage_banks(): void
    {
        $this->withSession(['user_role' => 'user'])
            ->get(route('bank.index'))
            ->assertForbidden();

        $this->withSession(['user_role' => 'user'])
            ->post(route('bank.store'), [
                'kode_bank' => 'BNI',
                'inisial' => 'BNI',
                'nama_bank' => 'BNI',
                'status' => 'aktif',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('banks', 0);
    }
}
