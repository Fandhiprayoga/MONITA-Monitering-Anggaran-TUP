<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanHistory;
use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanHistoryTest extends TestCase
{
    use RefreshDatabase;

    private PencairanActor $admin;
    private PencairanPengajuan $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([])
            ->shouldReceive('getPencairanDrkOptions')->andReturn([]);
        $this->admin = PencairanActor::create([
            'source_key' => 'history-admin', 'username' => 'admin', 'name' => 'Admin Sekarang', 'role' => 'admin',
        ]);
        $this->pengajuan = PencairanPengajuan::create([
            'jenis' => 'beban', 'status' => 'completed', 'kode_pp' => 'SIF', 'nama_pp' => 'Unit SIF',
            'created_by_actor_id' => $this->admin->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
            'nomor_pengajuan' => 'HISTORY-001', 'uraian_pengajuan' => 'Pembayaran kegiatan',
        ]);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
    }

    private function event(string $action, string $time, ?PencairanPengajuan $pengajuan = null): PencairanHistory
    {
        return ($pengajuan ?? $this->pengajuan)->histories()->create([
            'action' => $action, 'from_status' => 'submitted',
            'to_status' => $action === 'complete' ? 'completed' : 'verified',
            'actor_id' => $this->admin->id, 'actor_name' => 'Admin Saat Aktivitas', 'actor_role' => 'admin',
            'created_at' => $time,
        ]);
    }

    public function test_history_lists_events_not_current_status_with_stable_newest_first_order(): void
    {
        $verified = $this->event('verify', '2026-11-10 09:00:00');
        $completed = $this->event('complete', '2026-11-12 14:30:00');
        $sameTime = $this->event('verify', '2026-11-12 14:30:00');
        $this->event('save', '2026-11-13 12:00:00');
        $this->event('return', '2026-11-13 12:01:00');
        $this->pengajuan->completion()->create([
            'completion_date' => '2026-11-11', 'actor_id' => $this->admin->id,
        ]);
        $otherUnit = $this->pengajuan->replicate(['uuid', 'nomor_pengajuan']);
        $otherUnit->fill(['kode_pp' => 'AKA', 'nama_pp' => 'Unit AKA', 'nomor_pengajuan' => 'HISTORY-002'])->save();
        $old = $this->event('verify', '2026-11-09 08:00:00', $otherUnit);
        $this->pengajuan->update(['status' => 'returned', 'verified_at' => null, 'updated_at' => '2026-11-15 12:00:00']);

        $response = $this->get(route('pencairan.history'))->assertOk()
            ->assertViewIs('main.pencairan.history')
            ->assertSee('Admin Saat Aktivitas')->assertDontSee('Admin Sekarang')
            ->assertSee('11/11/2026')->assertSee('12/11/2026 14:30:00')
            ->assertSee('Unit AKA')->assertSee('Terverifikasi')->assertSee('Transaksi selesai')
            ->assertSee(route('pencairan.show', ['pengajuan' => $this->pengajuan, 'menu' => 'history']));
        $this->assertSame([$sameTime->id, $completed->id, $verified->id, $old->id],
            $response->viewData('items')->pluck('id')->all());
        $this->assertTrue($response->viewData('items')->every(
            fn ($event) => $event->relationLoaded('pengajuan') && $event->pengajuan->relationLoaded('completion')
        ));
        $this->assertSame('Unit AKA', $response->viewData('unitOptions')['AKA']);
    }

    public function test_date_range_includes_both_whole_days_and_supports_one_sided_ranges(): void
    {
        $before = $this->event('verify', '2026-11-09 23:59:59');
        $first = $this->event('verify', '2026-11-10 00:00:00');
        $last = $this->event('complete', '2026-11-12 23:59:59');
        $after = $this->event('verify', '2026-11-13 00:00:00');
        $this->pengajuan->completion()->create(['completion_date' => '2026-11-01', 'actor_id' => $this->admin->id]);
        $cases = [
            [['start_date' => '2026-11-10', 'end_date' => '2026-11-12'], [$last->id, $first->id]],
            [['start_date' => '2026-11-12', 'end_date' => '2026-11-12'], [$last->id]],
            [['start_date' => '2026-11-10'], [$after->id, $last->id, $first->id]],
            [['end_date' => '2026-11-12'], [$last->id, $first->id, $before->id]],
            [['start_date' => '', 'end_date' => '2026-11-12'], [$last->id, $first->id, $before->id]],
        ];
        foreach ($cases as [$filters, $expected]) {
            $response = $this->get(route('pencairan.history', $filters))->assertOk();
            $this->assertSame($expected, $response->viewData('items')->pluck('id')->all());
        }
    }

    public function test_filters_combine_and_empty_results_are_explained(): void
    {
        $verified = $this->event('verify', '2026-11-10 09:00:00');
        $completed = $this->event('complete', '2026-11-12 09:00:00');
        foreach ([
            [['action' => 'verify'], [$verified->id]],
            [['action' => 'complete', 'jenis' => 'beban', 'pp' => 'sif', 'q' => 'HISTORY-001'], [$completed->id]],
            [['q' => 'kegiatan'], [$completed->id, $verified->id]],
            [['jenis' => 'honor'], []],
            [['pp' => 'AKA'], []],
            [['q' => 'NO-MATCH'], []],
        ] as [$filters, $expected]) {
            $response = $this->get(route('pencairan.history', $filters))->assertOk();
            $this->assertSame($expected, $response->viewData('items')->pluck('id')->all());
            if (!$expected) {
                $response->assertSee('Belum ada aktivitas verifikasi atau transaksi selesai untuk filter ini.');
            }
        }
    }

    public function test_invalid_filters_are_rejected(): void
    {
        foreach ([
            ['start_date' => 'invalid'], ['start_date' => '2026-02-30'], ['end_date' => '12/11/2026'],
            ['start_date' => '2026-11-12', 'end_date' => '2026-11-10'],
            ['action' => 'save'], ['jenis' => 'invalid'], ['pp' => ['SIF']], ['q' => ['test']],
            ['q' => str_repeat('a', 101)], ['per_page' => '20'], ['per_page' => ['10']],
        ] as $filters) {
            $this->getJson(route('pencairan.history', $filters))->assertUnprocessable();
        }
        $this->from(route('pencairan.history'))->get(route('pencairan.history', ['start_date' => 'invalid']))
            ->assertRedirect(route('pencairan.history'))->assertSessionHasErrors('start_date');
        $this->get(route('pencairan.history'))->assertOk()->assertSee('alert-danger');
    }

    public function test_pagination_shares_preferences_and_retains_filters(): void
    {
        for ($i = 0; $i < 105; $i++) {
            $this->event('verify', '2026-11-12 09:00:00');
        }
        $filters = ['start_date' => '2026-11-12', 'end_date' => '2026-11-12',
            'action' => 'verify', 'jenis' => 'beban', 'pp' => 'SIF', 'q' => 'HISTORY'];
        foreach (['10' => 10, '50' => 50, '100' => 100, 'all' => 105] as $size => $count) {
            $response = $this->get(route('pencairan.history', $filters + ['per_page' => $size]))
                ->assertOk()->assertSessionHas('pencairan.per_page', (string) $size);
            $items = $response->viewData('items');
            $this->assertSame(105, $items->total());
            $this->assertSame($count, $items->count());
            $this->assertSame($count, $this->get(route('pencairan.history'))->assertOk()->viewData('items')->count());
            if ($size !== 'all') {
                parse_str(parse_url($items->nextPageUrl(), PHP_URL_QUERY), $query);
                foreach ($filters as $key => $value) {
                    $this->assertSame($value, $query[$key]);
                }
            }
        }
        $this->get(route('pencairan.pengajuan', ['per_page' => '50']))->assertOk();
        $response = $this->get(route('pencairan.history', $filters + ['page' => 2]))->assertOk();
        $this->assertSame(50, $response->viewData('items')->perPage());
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $form = '//form[@aria-label="Jumlah data history"]';
        $this->assertCount(0, $xpath->query($form.'//input[@name="page"]'));
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $xpath->query($form.'//input[@name="'.$key.'"]/@value')->item(0)->nodeValue);
        }
        $response = $this->get(route('pencairan.history', ['per_page' => 'all', 'page' => 9]))->assertOk();
        $this->assertSame(1, $response->viewData('items')->currentPage());
    }

    public function test_history_navigation_remains_active_on_detail_and_edit(): void
    {
        $this->event('verify', '2026-11-12 09:00:00');
        $response = $this->get(route('pencairan.history'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="pc-link active"\s+href="'.preg_quote(route('pencairan.history'), '/').'"/',
            $response->getContent()
        );
        foreach (['pencairan.show', 'pencairan.edit'] as $route) {
            $this->get(route($route, ['pengajuan' => $this->pengajuan, 'menu' => 'history']))
                ->assertOk()->assertViewHas('activeMenu', 'history')
                ->assertViewHas('backUrl', route('pencairan.history'));
        }
    }

    public function test_history_is_hidden_and_forbidden_for_non_admins_and_guests(): void
    {
        $this->withSession([
            'user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF'],
        ]);
        $this->get(route('pencairan.history'))->assertForbidden();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee(route('pencairan.history'));
        $this->get(route('pencairan.show', ['pengajuan' => $this->pengajuan, 'menu' => 'history']))
            ->assertOk()->assertViewHas('activeMenu', 'pengajuan');
        $this->flushSession();
        $this->get(route('pencairan.history'))->assertRedirect(route('login'));
    }
}
