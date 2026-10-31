<?php

namespace Tests\Feature;

use App\Services\GoogleSheetService;
use Database\Seeders\PencairanPengajuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_panel_shows_active_values_and_clear_links_without_page_parameter(): void
    {
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([]);
        $this->seed(PencairanPengajuanSeeder::class);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
        $response = $this->get(route('pencairan.pengajuan', [
            'q' => 'DUMMY', 'status' => 'completed', 'jenis' => 'honor',
            'tahun' => now()->year, 'tw' => (int) ceil(now()->month / 3), 'pp' => 'SIF',
        ]))->assertOk()->assertSee('1 pengajuan ditemukan')->assertSee('Filter aktif:')
            ->assertSee('Status: Selesai')->assertSee('Terapkan filter')->assertSee('Hapus semua')
            ->assertSee('SIF — Bagian Sistem Dan Teknologi Informasi')
            ->assertSee('id="filter-q"', false)->assertSee('id="filter-tahun"', false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@aria-label="Filter pengajuan"]//details[@open]')->length);
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

        $this->get(route('pencairan.pengajuan', ['status' => '']))->assertOk()
            ->assertSee('54 pengajuan ditemukan')->assertDontSee('aria-label="Filter aktif"', false);
        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF']]);
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee('18 pengajuan ditemukan')
            ->assertDontSee('id="filter-pp"', false);
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