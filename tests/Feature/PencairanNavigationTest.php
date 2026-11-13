<?php

namespace Tests\Feature;

use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Database\Seeders\PencairanPengajuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
    }

    public function test_each_transaction_menu_uses_its_own_view_and_retains_navigation_on_detail(): void
    {
        foreach (['imprestfund', 'beban', 'honor'] as $jenis) {
            $response = $this->get(route('pencairan.transaksi', ['jenis' => $jenis]).'?jenis=invalid')
                ->assertOk()->assertViewIs('main.pencairan.transaksi.'.$jenis);
            $items = $response->viewData('items');
            $this->assertGreaterThan(0, $items->total());
            foreach ($items as $item) {
                $this->assertSame($jenis, $item->jenis);
                $response->assertSee(route('pencairan.show', ['pengajuan' => $item, 'menu' => 'transaksi']));
            }
            $this->assertActiveMenu($response->getContent(), route('pencairan.transaksi', $jenis));

            $pengajuan = $items->first();
            foreach (['verified', 'completed', 'returned'] as $status) {
                $pengajuan->update(['status' => $status]);
                $detail = $this->get(route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'transaksi']))
                    ->assertOk()->assertViewHas('backUrl', route('pencairan.transaksi', $jenis))
                    ->assertSee('href="'.route('pencairan.transaksi', $jenis).'" class="btn btn-outline-secondary">Kembali ke Daftar', false);
                $this->assertActiveMenu($detail->getContent(), route('pencairan.transaksi', $jenis));
            }
        }
    }

    public function test_transaction_type_cannot_be_overridden_by_query_filter(): void
    {
        $response = $this->get(route('pencairan.transaksi', 'imprestfund').'?jenis=honor')->assertOk();
        $this->assertGreaterThan(0, $response->viewData('items')->total());
        foreach ($response->viewData('items') as $item) {
            $this->assertSame('imprestfund', $item->jenis);
        }
    }

    public function test_edit_retains_transaction_navigation_for_returned_own_submission(): void
    {
        $pengajuan = PencairanPengajuan::where('kode_pp', 'SIF')->where('status', 'returned')->firstOrFail();
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([])
            ->shouldReceive('getPencairanDrkOptions')->andReturn([]);
        $response = $this->get(route('pencairan.edit', ['pengajuan' => $pengajuan, 'menu' => 'transaksi']))
            ->assertOk()->assertViewHas('backUrl', route('pencairan.transaksi', $pengajuan->jenis))
            ->assertSee('data-menu="transaksi"', false);
        $this->assertActiveMenu($response->getContent(), route('pencairan.transaksi', $pengajuan->jenis));
    }

    public function test_verification_and_submission_navigation_remain_distinct(): void
    {
        $pengajuan = PencairanPengajuan::where('jenis', 'honor')->where('status', 'completed')->firstOrFail();
        $response = $this->get(route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'verifikasi']))
            ->assertOk()->assertViewHas('backUrl', route('pencairan.verifikasi'));
        $this->assertActiveMenu($response->getContent(), route('pencairan.verifikasi'));

        foreach ([null, 'invalid'] as $menu) {
            $response = $this->get(route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => $menu]))
                ->assertOk()->assertViewHas('backUrl', route('pencairan.pengajuan', ['jenis' => 'honor', 'tab' => 'nonaktif']));
            $this->assertActiveMenu($response->getContent(), route('pencairan.pengajuan'));
        }

        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => $pengajuan->kode_pp]]);
        $this->get(route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'transaksi']))
            ->assertOk()->assertViewHas('activeMenu', 'pengajuan');
        $this->get(route('pencairan.transaksi', 'honor'))->assertForbidden();
    }

    private function assertActiveMenu(string $html, string $url): void
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $links = (new \DOMXPath($document))->query('//nav[contains(@class,"pc-sidebar")]//a[contains(concat(" ",normalize-space(@class)," ")," active ")]');
        $this->assertCount(1, $links);
        $this->assertSame($url, $links->item(0)->getAttribute('href'));
    }
}
