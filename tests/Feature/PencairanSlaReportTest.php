<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanHistory;
use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanSlaReportTest extends TestCase
{
    use RefreshDatabase;

    private PencairanActor $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GoogleSheetService::class)->shouldReceive('getUnitsFromSheet')->andReturn([])
            ->shouldReceive('getPencairanDrkOptions')->andReturn([]);
        $this->admin = PencairanActor::create([
            'source_key' => 'report-admin', 'username' => 'admin', 'name' => 'Admin', 'role' => 'admin',
        ]);
        $this->withSession([
            'user_authenticated' => true, 'user_role' => 'admin',
            'user_data' => ['username' => 'admin', 'role' => 'admin', 'kode_pp' => 'SIF'],
        ]);
    }

    private function pengajuan(string $jenis = 'beban', string $status = 'completed', string $unit = 'SIF'): PencairanPengajuan
    {
        return PencairanPengajuan::create([
            'jenis' => $jenis, 'status' => $status, 'kode_pp' => $unit, 'nama_pp' => 'Unit '.$unit,
            'created_by_actor_id' => $this->admin->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
            'nomor_pengajuan' => 'REPORT-'.(PencairanPengajuan::count() + 1),
            'uraian_pengajuan' => 'Kegiatan '.$jenis,
        ]);
    }

    private function event(PencairanPengajuan $pengajuan, string $action, string $date, int $revision = 1): PencairanHistory
    {
        return $pengajuan->histories()->create([
            'action' => $action, 'revision_no' => $revision,
            'from_status' => match ($action) {
                'submit' => 'draft', 'verify' => 'submitted', default => 'verified',
            },
            'to_status' => match ($action) {
                'submit' => 'submitted', 'verify' => 'verified', 'return' => 'returned', default => 'completed',
            },
            'actor_id' => $this->admin->id, 'actor_name' => 'Admin', 'actor_role' => 'admin',
            'created_at' => $date,
        ]);
    }

    private function rows(array $filters = [])
    {
        return $this->get(route('pencairan.sla-report', $filters))->assertOk()->viewData('items')->getCollection();
    }

    public function test_report_measures_all_four_stages_and_keeps_finished_verification_for_pending_transactions(): void
    {
        foreach (['imprestfund', 'beban', 'honor'] as $jenis) {
            $p = $this->pengajuan($jenis);
            $this->event($p, 'submit', '2026-11-12 08:00:00');
            $this->event($p, 'verify', '2026-11-16 10:00:00');
            $this->event($p, 'complete', $jenis === 'imprestfund' ? '2026-11-19 10:00:00' : '2026-11-23 23:59:59');
            $p->completion()->create(['completion_date' => '2026-11-16', 'actor_id' => $this->admin->id]);
        }
        $pending = $this->pengajuan('honor', 'verified');
        $this->event($pending, 'submit', '2026-11-16 08:00:00');
        $this->event($pending, 'verify', '2026-11-19 10:00:00');
        $inProgress = $this->pengajuan('beban', 'submitted');
        $this->event($inProgress, 'submit', '2026-11-16 08:00:00');

        $response = $this->get(route('pencairan.sla-report'))->assertOk()
            ->assertViewIs('main.pencairan.sla-report')->assertSee('Report SLA per Tahap')
            ->assertSee('Transaksi Imprestfund')->assertSee('Transaksi Beban')->assertSee('Transaksi Honor')
            ->assertSee('data-bs-target="#modalFilterReportSla"', false)
            ->assertSee('data-bs-target="#modalHelpReportSla"', false)
            ->assertSee(route('pencairan.show', ['pengajuan' => $pending, 'menu' => 'sla-report']));
        $summary = $response->viewData('summary');
        $this->assertSame(7, $summary['total']);
        $this->assertSame(5, $summary['on_time']);
        $this->assertSame(2, $summary['late']);
        $this->assertSame(71.4, $summary['compliance']);
        $stages = $response->viewData('stageSummary');
        $this->assertSame(4, $stages['verifikasi']['total']);
        $this->assertSame(2, $stages['transaksi_imprestfund']['target_days']);
        $this->assertSame(5, $stages['transaksi_beban']['target_days']);
        $this->assertSame(5, $stages['transaksi_honor']['target_days']);
        $this->assertSame(2.3, $stages['verifikasi']['average_days']);
        $imprestfund = $this->rows(['tahap' => 'transaksi_imprestfund'])->sole();
        $this->assertSame('2026-11-18', $imprestfund['due_date']->toDateString());
        $this->assertSame(3, $imprestfund['duration_days']);
        $this->assertSame(1, $imprestfund['overdue_days']);
        $this->assertSame('terlambat', $imprestfund['result']);
        foreach (['transaksi_beban', 'transaksi_honor'] as $stage) {
            $row = $this->rows(['tahap' => $stage])->sole();
            $this->assertSame('tepat_waktu', $row['result']);
            $this->assertSame(5, $row['duration_days']);
            $this->assertSame('2026-11-23', $row['due_date']->toDateString());
        }
    }

    public function test_returns_and_resubmits_do_not_erase_old_results_or_pair_different_cycles(): void
    {
        $p = $this->pengajuan('beban', 'returned');
        $this->event($p, 'submit', '2026-11-02 08:00:00');
        $v1 = $this->event($p, 'verify', '2026-11-06 09:00:00');
        $this->event($p, 'return', '2026-11-06 10:00:00');
        $this->event($p, 'submit', '2026-11-09 08:00:00', 2);
        $v2 = $this->event($p, 'verify', '2026-11-10 09:00:00', 2);
        $this->event($p, 'return', '2026-11-10 10:00:00', 2);
        $this->event($p, 'submit', '2026-11-12 08:00:00', 3);
        $this->event($p, 'verify', '2026-11-16 09:00:00', 3);
        $complete = $this->event($p, 'complete', '2026-11-23 10:00:00', 3);
        $p->update(['status' => 'completed', 'verified_at' => '2026-11-16', 'last_submitted_at' => '2026-11-12']);

        $rows = $this->rows();
        $this->assertCount(4, $rows);
        $byEvent = $rows->keyBy(fn ($row) => $row['event']->id);
        $this->assertSame('terlambat', $byEvent[$v1->id]['result']);
        $this->assertSame(4, $byEvent[$v1->id]['duration_days']);
        $this->assertSame('tepat_waktu', $byEvent[$v2->id]['result']);
        $this->assertSame('2026-11-09', $byEvent[$v2->id]['started_at']->toDateString());
        $this->assertSame('2026-11-16', $byEvent[$complete->id]['started_at']->toDateString());
        $this->assertSame(5, $byEvent[$complete->id]['duration_days']);
    }

    public function test_incomplete_mismatched_and_reversed_histories_are_visible_but_excluded_from_compliance(): void
    {
        $missing = $this->pengajuan();
        $this->event($missing, 'complete', '2026-11-16 10:00:00');
        $mismatch = $this->pengajuan();
        $this->event($mismatch, 'submit', '2026-11-12 10:00:00');
        $this->event($mismatch, 'verify', '2026-11-16 10:00:00', 2);
        $reversed = $this->pengajuan();
        $this->event($reversed, 'submit', '2026-11-17 10:00:00');
        $this->event($reversed, 'verify', '2026-11-16 10:00:00');
        $good = $this->pengajuan();
        $this->event($good, 'submit', '2026-11-12 10:00:00');
        $this->event($good, 'verify', '2026-11-16 10:00:00');

        $response = $this->get(route('pencairan.sla-report'))->assertOk()
            ->assertSee('Riwayat awal pada siklus yang sama tidak tersedia.')
            ->assertSee('Waktu selesai mendahului waktu mulai.')
            ->assertSee('tidak masuk perhitungan kepatuhan');
        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['unmeasurable']);
        $this->assertSame(100.0, $summary['compliance']);
        $this->assertSame(2.0, $summary['average_days']);
        $missingResponse = $this->get(route('pencairan.sla-report', ['hasil' => 'data_missing']))->assertOk();
        $this->assertCount(3, $missingResponse->viewData('items'));
        $this->assertNull($missingResponse->viewData('summary')['compliance']);
        $this->assertNull($missingResponse->viewData('summary')['average_days']);
        $empty = $this->get(route('pencairan.sla-report', ['hasil' => 'terlambat']))->assertOk()
            ->assertSee('Belum ada tahap selesai untuk filter laporan ini.');
        $this->assertNull($empty->viewData('summary')['compliance']);
    }

    public function test_date_filter_uses_whole_finish_days_and_pairs_starts_outside_report_period(): void
    {
        $p = $this->pengajuan('imprestfund', 'completed', 'AKA');
        $this->event($p, 'submit', '2026-10-29 08:00:00');
        $this->event($p, 'verify', '2026-10-30 08:00:00');
        $c = $this->event($p, 'complete', '2026-11-02 23:59:59');
        $p->completion()->create(['completion_date' => '2026-10-30', 'actor_id' => $this->admin->id]);
        $other = $this->pengajuan();
        $this->event($other, 'submit', '2026-11-02 09:00:00');
        $this->event($other, 'verify', '2026-11-03 00:00:00');
        $rows = $this->rows(['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'pp' => 'aka', 'q' => $p->nomor_pengajuan]);
        $this->assertSame($c->id, $rows->sole()['event']->id);
        $this->assertSame(1, $rows->sole()['duration_days']);
        $this->assertSame('2026-10-30', $rows->sole()['started_at']->toDateString());
        $this->assertCount(0, $this->rows(['end_date' => '2026-10-28']));
        $this->assertCount(1, $this->rows(['start_date' => '2026-11-03']));
    }

    public function test_pagination_preserves_filters_and_summary_counts_all_matching_rows(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $p = $this->pengajuan();
            $this->event($p, 'submit', '2026-11-12 09:00:00');
            $this->event($p, 'verify', '2026-11-16 09:00:00');
        }
        $response = $this->get(route('pencairan.sla-report', ['tahap' => 'verifikasi', 'per_page' => 10, 'page' => 2]))->assertOk();
        $this->assertSame(12, $response->viewData('summary')['total']);
        $items = $response->viewData('items');
        $this->assertCount(2, $items);
        $this->assertSame(12, $items->total());
        $this->assertStringContainsString('tahap=verifikasi', $items->url(1));
        $this->assertStringContainsString('per_page=10', $items->url(1));
        $this->assertGreaterThan($items->getCollection()[1]['event']->id, $items->getCollection()[0]['event']->id);
    }

    public function test_report_menu_and_detail_navigation_are_admin_only(): void
    {
        $p = $this->pengajuan();
        $response = $this->get(route('pencairan.sla-report'))->assertOk()->assertViewHas('activeMenu', 'sla-report');
        $this->assertMatchesRegularExpression(
            '/class="pc-link active"\s+href="'.preg_quote(route('pencairan.sla-report'), '/').'"/',
            $response->getContent()
        );
        $response->assertSeeInOrder(['Transaksi', 'Report SLA', 'Monitoring SLA', 'History']);
        $this->get(route('pencairan.show', ['pengajuan' => $p, 'menu' => 'sla-report']))
            ->assertOk()->assertViewHas('backUrl', route('pencairan.sla-report'))
            ->assertViewHas('activeMenu', 'sla-report');
        $this->get(route('pencairan.edit', ['pengajuan' => $p, 'menu' => 'sla-report']))
            ->assertOk()->assertViewHas('activeMenu', 'sla-report');
        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF']]);
        $this->get(route('pencairan.sla-report'))->assertForbidden();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee(route('pencairan.sla-report'));
        $this->get(route('pencairan.show', ['pengajuan' => $p, 'menu' => 'sla-report']))
            ->assertOk()->assertViewHas('activeMenu', 'pengajuan');
        $this->flushSession();
        $this->get(route('pencairan.sla-report'))->assertRedirect(route('login'));
    }

    public function test_weekend_finish_after_deadline_is_late_without_inventing_a_business_day(): void
    {
        $p = $this->pengajuan('imprestfund');
        $this->event($p, 'submit', '2026-11-11 08:00:00');
        $this->event($p, 'verify', '2026-11-11 09:00:00');
        $this->event($p, 'complete', '2026-11-14 08:00:00');
        $row = $this->rows(['tahap' => 'transaksi_imprestfund'])->sole();
        $this->assertSame('2026-11-13', $row['due_date']->toDateString());
        $this->assertSame(2, $row['duration_days']);
        $this->assertSame(0, $row['overdue_days']);
        $this->assertSame('terlambat', $row['result']);
        $this->assertSame(0, $this->rows(['tahap' => 'verifikasi'])->sole()['duration_days']);
    }

    public function test_invalid_filters_are_reported(): void
    {
        foreach ([
            ['tahap' => 'unknown'], ['hasil' => 'aman'], ['start_date' => '2026-02-30'],
            ['start_date' => '2026-11-18', 'end_date' => '2026-11-17'],
            ['per_page' => 0], ['page' => -1], ['pp' => ['SIF']], ['q' => str_repeat('x', 101)],
        ] as $filters) {
            $this->getJson(route('pencairan.sla-report', $filters))->assertUnprocessable();
        }
    }
}
