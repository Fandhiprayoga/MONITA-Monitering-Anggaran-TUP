<?php

namespace Tests\Feature;

use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Database\Seeders\PencairanPengajuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_size_options_limit_rows_and_persist_without_query_parameter(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $draft = PencairanPengajuan::where('status', 'draft')->firstOrFail();
        for ($i = 0; $i < 75; $i++) {
            $draft->replicate(['uuid'])->save();
        }
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
        $url = route('pencairan.pengajuan');
        $this->assertSame(10, $this->get($url)->assertOk()->viewData('items')->count());

        foreach (['10' => 10, '50' => 50, '100' => 100, 'all' => 111] as $value => $count) {
            $response = $this->get($url.'?per_page='.$value)->assertOk()
                ->assertSessionHas('pencairan.per_page', (string) $value);
            $items = $response->viewData('items');
            $this->assertSame(111, $items->total());
            $this->assertSame($count, $items->count());
            $this->assertSame($value !== 'all', $items->hasMorePages());
            $response = $this->get($url)->assertOk();
            $this->assertSame($count, $response->viewData('items')->count());
            $this->assertMatchesRegularExpression(
                '/<option value="'.preg_quote((string) $value, '/').'"[^>]*selected/', $response->getContent()
            );
        }

        $items = $this->get($url.'?page=9')->assertOk()->viewData('items');
        $this->assertSame(111, $items->count());
        $this->assertSame(1, $items->currentPage());
        $items = $this->get($url.'?tab=nonaktif')->assertOk()->viewData('items');
        $this->assertSame(18, $items->count());
        $items = $this->get($url.'?q=NO-MATCH')->assertOk()->viewData('items');
        $this->assertSame(0, $items->count());

        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'AKA']]);
        $items = $this->get($url)->assertOk()->viewData('items');
        $this->assertSame(12, $items->count());
        $this->assertTrue($items->getCollection()->every(fn ($item) => $item->kode_pp === 'AKA'));
    }

    public function test_page_size_control_preserves_filters_but_resets_page(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
        $filters = ['tab' => 'nonaktif', 'q' => 'DUMMY', 'status' => 'completed',
            'jenis' => 'honor', 'tahun' => '2026', 'tw' => '1', 'pp' => 'SIF'];
        $response = $this->get(route('pencairan.pengajuan', $filters + ['page' => 3, 'per_page' => '50']))
            ->assertOk();
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $form = '//form[@aria-label="Jumlah data pengajuan"]';
        $this->assertSame(0, $xpath->query($form.'//input[@name="page"]')->length);
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $xpath->query($form.'//input[@name="'.$key.'"]/@value')->item(0)->nodeValue);
        }
        $options = $xpath->query($form.'//select[@name="per_page"]/option/@value');
        $this->assertSame(['10', '50', '100', 'all'], array_map(fn ($node) => $node->nodeValue, iterator_to_array($options)));

        $response = $this->get(route('pencairan.verifikasi'))->assertOk()
            ->assertSee('id="pengajuan-per-page"', false);
        $this->assertSame(50, $response->viewData('items')->perPage());
    }

    public function test_all_lists_share_page_size_and_migrate_the_previous_preference(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $draft = PencairanPengajuan::where('status', 'submitted')->firstOrFail();
        for ($i = 0; $i < 105; $i++) {
            $draft->replicate(['uuid', 'nomor_pengajuan', 'nomor_urut', 'tahun_nomor'])->save();
        }
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
            'pencairan' => ['pengajuan' => ['per_page' => '50']],
        ]);
        $urls = [
            route('pencairan.verifikasi'), route('pencairan.pengajuan'),
            route('pencairan.transaksi', 'impresfund'), route('pencairan.transaksi', 'beban'),
            route('pencairan.transaksi', 'honor'),
        ];
        $this->get($urls[0])->assertOk()->assertSessionHas('pencairan.per_page', '50')
            ->assertSessionMissing('pencairan.pengajuan.per_page');
        foreach (['10', '50', '100', 'all'] as $index => $value) {
            $this->get($urls[$index].'?per_page='.$value)->assertOk()
                ->assertSessionHas('pencairan.per_page', $value);
            foreach ($urls as $url) {
                $response = $this->get($url)->assertOk()->assertSee('id="pengajuan-per-page"', false);
                $this->assertSame($value, $response->viewData('perPage'));
                $items = $response->viewData('items');
                $this->assertSame($value === 'all' ? $items->total() : min((int) $value, $items->total()), $items->count());
                $this->assertMatchesRegularExpression(
                    '/<option value="'.$value.'"[^>]*selected/', $response->getContent()
                );
            }
        }
    }

    public function test_invalid_page_size_is_rejected_without_overwriting_saved_preference(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
            'pencairan' => ['per_page' => '50'],
        ]);
        foreach (['0', '-1', '20', '1000', 'invalid', '', ['10']] as $value) {
            foreach ([
                route('pencairan.pengajuan', ['per_page' => $value]),
                route('pencairan.verifikasi', ['per_page' => $value]),
                route('pencairan.transaksi', ['jenis' => 'honor', 'per_page' => $value]),
            ] as $url) {
                $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('per_page')
                    ->assertSessionHas('pencairan.per_page', '50');
            }
        }
    }

    public function test_filter_panel_shows_active_values_and_clear_links_without_page_parameter(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
        $response = $this->get(route('pencairan.pengajuan', [
            'tab' => 'nonaktif', 'q' => 'DUMMY', 'status' => 'completed', 'jenis' => 'honor',
            'tahun' => now()->year, 'tw' => (int) ceil(now()->month / 3), 'pp' => 'SIF',
        ]))->assertOk()->assertSee('Filter aktif:')
            ->assertSee('Status: Selesai')->assertSee('Terapkan filter')->assertSee('Hapus semua')
            ->assertSee('SIF — Bagian Sistem Dan Teknologi Informasi')
            ->assertSee('id="filter-q"', false)->assertSee('id="filter-tahun"', false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[@id="modalFilter"]//form[@aria-label="Filter pengajuan"]')->length);
        $this->assertSame(4, $xpath->query('//form[@aria-label="Filter pengajuan"]//div[contains(@class,"pencairan-filter-fields")]/*')->length);
        $chips = $xpath->query('//a[@class="pencairan-filter-chip"]');
        $this->assertSame(6, $chips->length);
        foreach ($chips as $chip) {
            $this->assertInstanceOf(\DOMElement::class, $chip);
            parse_str(parse_url($chip->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('page', $query);
            if ($chip->getAttribute('aria-label') === 'Hapus filter Status: Selesai') {
                $this->assertSame('', $query['status']);
                $this->assertSame('honor', $query['jenis']);
            }
        }

        $response = $this->get(route('pencairan.pengajuan', ['status' => '']))->assertOk()
            ->assertDontSee('aria-label="Filter aktif"', false);
        $this->assertSame(36, $response->viewData('items')->total());
        $this->assertSame(18, $this->get(route('pencairan.pengajuan', ['tab' => 'nonaktif']))->assertOk()->viewData('items')->total());
        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF']]);
        $response = $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee('id="filter-pp"', false);
        $this->assertSame(12, $response->viewData('items')->total());
    }

    public function test_list_uses_bootstrap_pagination_and_preserves_filters_on_next_page(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF', 'nama_pp' => 'SIF'],
        ]);

        foreach ([1, 2] as $page) {
            $response = $this->get(route('pencairan.pengajuan', ['q' => 'DUMMY', 'page' => $page]))
                ->assertOk()->assertSee('class="pagination"', false)
                ->assertSee('page-item active', false)->assertSee('page-link', false)
                ->assertDontSee('class="w-5 h-5"', false);

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//ul[@class="pagination"]//a[@href]');
            $this->assertGreaterThan(0, $links->length);
            foreach ($links as $link) {
                $this->assertInstanceOf(\DOMElement::class, $link);
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                $this->assertSame('DUMMY', $query['q']);
            }
        }
    }
}