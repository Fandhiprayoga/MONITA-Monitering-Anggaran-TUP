<?php

namespace Tests\Feature;

use App\Models\PencairanActor;
use App\Models\PencairanHistory;
use App\Models\PencairanPengajuan;
use App\Services\GoogleSheetService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanSlaReportTest extends TestCase
{
    use RefreshDatabase;

    private PencairanActor $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 13:30:00', config('app.timezone')));
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

    private function pengajuan(
        string $jenis = 'beban',
        string $status = 'completed',
        ?string $submitted = '2026-11-12 08:00:00',
        string $unit = 'SIF',
        array $attributes = [],
    ): PencairanPengajuan {
        return PencairanPengajuan::create($attributes + [
            'jenis' => $jenis, 'status' => $status, 'kode_pp' => $unit, 'nama_pp' => 'Unit '.$unit,
            'created_by_actor_id' => $this->admin->id, 'tahun_anggaran' => 2026, 'triwulan' => 4,
            'nomor_pengajuan' => 'REPORT-'.(PencairanPengajuan::count() + 1),
            'uraian_pengajuan' => 'Kegiatan '.$jenis,
            'first_submitted_at' => $submitted, 'last_submitted_at' => $submitted,
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
        return $this->get(route('pencairan.sla-report', ['tab' => 'detail'] + $filters))
            ->assertOk()->viewData('items')->getCollection();
    }

    public function test_overview_counts_unique_submissions_and_all_four_stages(): void
    {
        foreach (['imprestfund', 'beban', 'honor'] as $jenis) {
            $p = $this->pengajuan($jenis);
            $this->event($p, 'submit', '2026-11-12 08:00:00');
            $this->event($p, 'verify', '2026-11-16 10:00:00');
            $this->event($p, 'complete', $jenis === 'imprestfund' ? '2026-11-19 10:00:00' : '2026-11-23 23:59:59');
            $p->completion()->create(['completion_date' => '2026-11-16', 'actor_id' => $this->admin->id]);
        }
        $pending = $this->pengajuan('honor', 'verified', '2026-11-16 08:00:00', attributes: ['verified_at' => '2026-11-19 10:00:00']);
        $this->event($pending, 'submit', '2026-11-16 08:00:00');
        $this->event($pending, 'verify', '2026-11-19 10:00:00');
        $inProgress = $this->pengajuan('beban', 'submitted', '2026-11-16 08:00:00');
        $this->event($inProgress, 'submit', '2026-11-16 08:00:00');

        $response = $this->get(route('pencairan.sla-report'))->assertOk()
            ->assertViewIs('main.pencairan.sla-report')->assertSee('Overview')->assertSee('Detail Pengajuan')
            ->assertSee('Transaksi Imprestfund')->assertSee('Transaksi Beban')->assertSee('Transaksi Honor')
            ->assertSee('data-bs-target="#modalFilterReportSla"', false)
            ->assertSee('data-bs-target="#modalHelpReportSla"', false)
            ->assertSee('Arti “Belum Dapat Disimpulkan”')
            ->assertSee('Dikembalikan atau dibatalkan setelah submit')
            ->assertSee('bukan sebagai hasil akhir')
            ->assertDontSee('Durasi Verifikasi');
        $this->assertSame(['total' => 5, 'tepat_waktu' => 2, 'terlambat' => 2, 'undetermined' => 1], $response->viewData('summary'));
        $stages = $response->viewData('stageSummary');
        $this->assertSame(75.0, $stages['verifikasi']['compliance']);
        $this->assertSame(1, $stages['verifikasi']['active_overdue']);
        $this->assertSame(1, $stages['transaksi_honor']['active']);
        $this->assertSame(0, $stages['transaksi_honor']['active_overdue']);
        $this->assertSame(100.0, $stages['transaksi_honor']['compliance']);
        $this->assertSame([[2], [2], [5], [5]], array_map('array_keys', array_column($stages, 'targets')));

        $detail = $this->get(route('pencairan.sla-report', ['tab' => 'detail']))->assertOk()
            ->assertSee('Tgl Verifikasi')->assertSee('Tgl Transaksi')->assertSee('Tgl Selesai')
            ->assertSee('Durasi Verifikasi')->assertSee('Durasi Transaksi')->assertSee('Masih berjalan — lewat tenggat')
            ->assertDontSee('Total Pengajuan');
        $items = $detail->viewData('items');
        $this->assertSame(5, $items->total());
        $rows = $items->getCollection()->keyBy('pengajuan.id');
        $imprestfund = $rows->firstWhere('transaction_stage', 'transaksi_imprestfund');
        $transaction = $imprestfund['stages']['transaksi_imprestfund'];
        $this->assertSame('2026-11-16', $imprestfund['pengajuan']->completion->completion_date->toDateString());
        $this->assertSame('2026-11-19', $transaction['finished_at']->toDateString());
        $this->assertSame('2026-11-18', $transaction['due_date']->toDateString());
        $this->assertSame(3, $transaction['duration_days']);
        $this->assertSame('terlambat', $transaction['result']);
        $this->assertNull($rows[$pending->id]['stages']['transaksi_honor']);
        foreach (['transaksi_beban', 'transaksi_honor'] as $stage) {
            $row = $rows->first(fn ($row) => $row['transaction_stage'] === $stage && $row['result'] === 'tepat_waktu');
            $this->assertSame(5, $row['stages'][$stage]['duration_days']);
            $this->assertSame('2026-11-23', $row['stages'][$stage]['due_date']->toDateString());
        }
    }

    public function test_resubmit_keeps_only_last_finished_result_and_reports_the_new_active_cycle(): void
    {
        $p = $this->pengajuan(status: 'submitted', submitted: '2026-11-02 08:00:00', attributes: ['last_submitted_at' => '2026-11-20 08:00:00']);
        $this->event($p, 'submit', '2026-11-02 08:00:00');
        $this->event($p, 'verify', '2026-11-06 09:00:00');
        $this->event($p, 'return', '2026-11-06 10:00:00');
        $this->event($p, 'submit', '2026-11-09 08:00:00', 2);
        $v2 = $this->event($p, 'verify', '2026-11-10 09:00:00', 2);
        $this->event($p, 'return', '2026-11-10 10:00:00', 2);
        $this->event($p, 'submit', '2026-11-20 08:00:00', 3);

        $row = $this->rows()->sole();
        $verification = $row['stages']['verifikasi'];
        $this->assertSame($v2->id, $verification['event']->id);
        $this->assertSame('2026-11-09', $verification['started_at']->toDateString());
        $this->assertSame(1, $verification['duration_days']);
        $this->assertSame('2026-11-24', $row['active']['due_date']->toDateString());
        $response = $this->get(route('pencairan.sla-report'))->assertOk();
        $this->assertSame(['total' => 1, 'tepat_waktu' => 0, 'terlambat' => 0, 'undetermined' => 1], $response->viewData('summary'));
        $stage = $response->viewData('stageSummary')['verifikasi'];
        $this->assertSame(1, $stage['tepat_waktu']);
        $this->assertSame(0, $stage['terlambat']);
        $this->assertSame(1, $stage['active']);
        $this->assertSame(100.0, $stage['compliance']);

        $p->update(['status' => 'completed']);
        $this->event($p, 'verify', '2026-11-23 09:00:00', 3);
        $this->event($p, 'complete', '2026-11-24 10:00:00', 3);
        $transaction = $this->rows()->sole()['stages']['transaksi_beban'];
        $this->assertSame('2026-11-23', $transaction['started_at']->toDateString());
        $this->assertSame(1, $transaction['duration_days']);
    }

    public function test_invalid_or_missing_history_is_visible_but_excluded_from_compliance(): void
    {
        $missing = $this->pengajuan();
        $this->event($missing, 'complete', '2026-11-16 10:00:00');
        $mismatch = $this->pengajuan(status: 'returned');
        $this->event($mismatch, 'submit', '2026-11-12 10:00:00');
        $this->event($mismatch, 'verify', '2026-11-16 10:00:00', 2);
        $reversed = $this->pengajuan(status: 'returned');
        $this->event($reversed, 'submit', '2026-11-17 10:00:00');
        $this->event($reversed, 'verify', '2026-11-16 10:00:00');
        $good = $this->pengajuan(status: 'returned');
        $this->event($good, 'submit', '2026-11-12 10:00:00');
        $this->event($good, 'verify', '2026-11-16 10:00:00');

        $this->get(route('pencairan.sla-report', ['tab' => 'detail']))->assertOk()
            ->assertSee('Riwayat selesai tahap tidak tersedia.')
            ->assertSee('Riwayat awal pada siklus yang sama tidak tersedia.')
            ->assertSee('Waktu selesai mendahului waktu mulai.')
            ->assertSee('Dikembalikan untuk Perbaikan');
        $stages = $this->get(route('pencairan.sla-report'))->assertOk()->viewData('stageSummary');
        $this->assertSame(3, $stages['verifikasi']['data_missing']);
        $this->assertSame(1, $stages['transaksi_beban']['data_missing']);
        $this->assertSame(100.0, $stages['verifikasi']['compliance']);
        $this->assertNull($stages['transaksi_beban']['compliance']);
    }

    public function test_no_default_period_and_explicit_submit_date_filters_are_inclusive_in_both_tabs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-31 13:30:00', config('app.timezone')));
        $old = $this->pengajuan(status: 'submitted', submitted: '2026-02-27 23:59:59', attributes: ['last_submitted_at' => '2026-03-30 10:00:00']);
        $first = $this->pengajuan(status: 'returned', submitted: '2026-02-28 00:00:00');
        $last = $this->pengajuan(status: 'cancelled', submitted: '2026-03-31 23:59:59');
        $future = $this->pengajuan(status: 'submitted', submitted: '2026-04-01 00:00:00');
        $this->pengajuan(status: 'draft', submitted: null);
        $this->event($first, 'submit', '2026-02-28 00:00:00');
        $this->event($first, 'verify', '2026-04-01 08:00:00');

        $unfiltered = $this->get(route('pencairan.sla-report'))->assertOk()
            ->assertDontSee('Filter aktif:')
            ->assertSee('name="start_date" type="date" class="form-control" value=""', false)
            ->assertSee('name="end_date" type="date" class="form-control" value=""', false)
            ->assertSee(route('pencairan.sla-report', ['tab' => 'detail']));
        $this->assertSame(4, $unfiltered->viewData('summary')['total']);
        $this->assertSame([$future->id, $last->id, $first->id, $old->id], $this->rows()->pluck('pengajuan.id')->all());
        $this->assertCount(4, $this->rows(['start_date' => '', 'end_date' => '']));

        $dates = ['start_date' => '2026-02-28', 'end_date' => '2026-03-31'];
        $overview = $this->get(route('pencairan.sla-report', $dates))->assertOk()
            ->assertSee('Submit awal mulai: 2026-02-28')
            ->assertSee('Submit awal sampai: 2026-03-31')
            ->assertSee(route('pencairan.sla-report', $dates + ['tab' => 'detail']));

        $this->assertSame([$last->id, $first->id], $this->rows($dates)->pluck('pengajuan.id')->all());
        $this->assertSame(2, $overview->viewData('summary')['total']);
        $this->assertSame(1, $overview->viewData('stageSummary')['verifikasi']['terlambat']);
        $this->assertSame([$old->id], $this->rows(['end_date' => '2026-02-27'])->pluck('pengajuan.id')->all());
        $this->assertSame([$future->id], $this->rows(['start_date' => '2026-04-01'])->pluck('pengajuan.id')->all());
    }

    public function test_filters_pagination_and_detail_navigation_are_consistent(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->pengajuan(status: 'submitted');
        }
        $this->pengajuan('honor', 'submitted');
        $this->pengajuan(status: 'submitted', unit: 'OTHER');
        $this->pengajuan(status: 'submitted', attributes: ['nomor_pengajuan' => 'EXCLUDED', 'uraian_pengajuan' => 'Other']);
        $filters = [
            'start_date' => '2026-10-24', 'end_date' => '2026-11-24',
            'jenis' => 'beban', 'pp' => 'sif', 'q' => 'REPORT-', 'per_page' => 10,
        ];
        $overview = $this->get(route('pencairan.sla-report', $filters))->assertOk();
        $this->assertSame(12, $overview->viewData('summary')['total']);
        $detail = $this->get(route('pencairan.sla-report', ['tab' => 'detail', 'page' => 2] + $filters))->assertOk();
        $items = $detail->viewData('items');
        $this->assertCount(2, $items);
        $this->assertSame(12, $items->total());
        $this->assertGreaterThan($items->getCollection()[1]['pengajuan']->id, $items->getCollection()[0]['pengajuan']->id);
        $query = $filters + ['tab' => 'detail'];
        parse_str(parse_url($items->url(1), PHP_URL_QUERY), $paging);
        $this->assertEquals($query + ['page' => '1'], $paging);
        $p = $items->getCollection()->first()['pengajuan'];
        $query['page'] = 2;
        unset($query['tab']);
        $url = route('pencairan.show', ['pengajuan' => $p, 'menu' => 'sla-report', 'page' => 2] + $query);
        $detail->assertSee($url);
        $backUrl = $this->get($url)->assertOk()->viewData('backUrl');
        $this->assertSame(route('pencairan.sla-report'), strtok($backUrl, '?'));
        parse_str(parse_url($backUrl, PHP_URL_QUERY), $backQuery);
        $this->assertEquals(['tab' => 'detail'] + $query, $backQuery);
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
            ->assertOk()->assertViewHas('backUrl', route('pencairan.sla-report', ['tab' => 'detail']))
            ->assertViewHas('activeMenu', 'sla-report');
        $this->get(route('pencairan.edit', ['pengajuan' => $p, 'menu' => 'sla-report']))
            ->assertOk()->assertViewHas('activeMenu', 'sla-report');
        $this->withSession(['user_role' => 'user', 'user_data' => ['username' => 'user', 'role' => 'user', 'kode_pp' => 'SIF']]);
        $this->get(route('pencairan.sla-report'))->assertForbidden();
        $this->get(route('pencairan.sla-report', ['tab' => 'detail']))->assertForbidden();
        $this->get(route('pencairan.sla-report.export'))->assertForbidden();
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee(route('pencairan.sla-report'));
        $this->get(route('pencairan.show', ['pengajuan' => $p, 'menu' => 'sla-report']))
            ->assertOk()->assertViewHas('activeMenu', 'pengajuan');
        $this->flushSession();
        $this->get(route('pencairan.sla-report'))->assertRedirect(route('login'));
        $this->get(route('pencairan.sla-report.export'))->assertRedirect(route('login'));
    }

    private function csvRows(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $header = fgetcsv($stream, 0, ',', '"', '');
        $rows = [];
        while (($fields = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $fields);
        }
        fclose($stream);

        return $rows;
    }

    public function test_csv_export_uses_all_active_filters_and_ignores_pagination(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->pengajuan(status: 'submitted');
        }
        $this->pengajuan('honor', 'submitted');
        $this->pengajuan(status: 'submitted', unit: 'OTHER');
        $this->pengajuan(status: 'submitted', attributes: ['nomor_pengajuan' => 'EXCLUDED', 'uraian_pengajuan' => 'Other']);
        $this->pengajuan(status: 'submitted', submitted: '2026-10-23 23:59:59');
        $this->pengajuan(status: 'submitted', submitted: '2026-11-25 00:00:00');
        $filters = [
            'start_date' => '2026-10-24', 'end_date' => '2026-11-24',
            'jenis' => 'beban', 'pp' => 'sif', 'q' => 'REPORT-',
        ];
        $detail = $this->get(route('pencairan.sla-report', ['tab' => 'detail', 'per_page' => 10, 'page' => 2] + $filters))
            ->assertOk()->assertSee(route('pencairan.sla-report.export', $filters));
        $this->assertSame(12, $detail->viewData('items')->total());
        $this->get(route('pencairan.sla-report'))->assertOk()->assertDontSee(route('pencairan.sla-report.export'));

        $response = $this->get(route('pencairan.sla-report.export', $filters + ['per_page' => 10, 'page' => 2]))->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('report-sla-detail.csv', $response->headers->get('Content-Disposition'));
        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(12, $rows);
        $this->assertSame('REPORT-12', $rows[0]['Nomor Pengajuan']);
        $this->assertSame('REPORT-1', $rows[11]['Nomor Pengajuan']);
        $this->assertSame('Masih berjalan — lewat tenggat', $rows[0]['Proses Verifikasi Aktif']);
        $this->assertSame('2', $rows[0]['Target Verifikasi Aktif (hari kerja)']);
        $this->assertSame('', $rows[0]['Durasi Verifikasi (hari kerja)']);

        $this->get(route('pencairan.sla-report.export', ['start_date' => '2026-11-24', 'end_date' => '2026-11-23']))
            ->assertSessionHasErrors('end_date');
        $this->assertSame([], $this->csvRows($this->get(route('pencairan.sla-report.export', ['q' => 'NOT-FOUND']))
            ->assertOk()->streamedContent()));
    }

    public function test_csv_export_matches_report_durations_and_preserves_safe_text(): void
    {
        $p = $this->pengajuan('imprestfund', submitted: '2026-11-13 08:00:00', attributes: [
            'nomor_pengajuan' => '=1+1',
            'uraian_pengajuan' => "  +formula, \"kutipan\"\nbaris kedua",
            'nama_pp' => '@Unit',
        ]);
        $this->event($p, 'submit', '2026-11-13 08:00:00');
        $this->event($p, 'verify', '2026-11-13 09:00:00');
        $this->event($p, 'complete', '2026-11-15 10:00:00');
        $p->completion()->create(['completion_date' => '2026-11-14', 'actor_id' => $this->admin->id]);
        $missing = $this->pengajuan(status: 'completed');
        $this->event($missing, 'complete', '2026-11-16 10:00:00');

        $rows = $this->csvRows($this->get(route('pencairan.sla-report.export'))->assertOk()->streamedContent());
        $this->assertCount(2, $rows);
        $row = $rows[0];
        $this->assertSame("'=1+1", $row['Nomor Pengajuan']);
        $this->assertSame("'  +formula, \"kutipan\"\nbaris kedua", $row['Uraian Pengajuan']);
        $this->assertSame("'@Unit", $row['Nama Unit']);
        $this->assertSame('13/11/2026', $row['Tgl Pengajuan']);
        $this->assertSame('13/11/2026', $row['Tgl Verifikasi']);
        $this->assertSame('14/11/2026', $row['Tgl Transaksi']);
        $this->assertSame('15/11/2026', $row['Tgl Selesai']);
        foreach (['Verifikasi', 'Transaksi'] as $stage) {
            $this->assertSame('1', $row["Durasi $stage (hari kerja)"]);
            $this->assertSame('Tepat waktu', $row["Hasil $stage"]);
            $this->assertSame('2', $row["Target $stage (hari kerja)"]);
        }
        $this->assertSame('Tidak dapat dihitung', $rows[1]['Hasil Transaksi']);
        $this->assertSame('', $rows[1]['Durasi Transaksi (hari kerja)']);
        $this->assertSame('Riwayat awal pada siklus yang sama tidak tersedia.', $rows[1]['Keterangan Transaksi']);
    }

    public function test_csv_export_preserves_order_across_chunks_and_includes_active_and_previous_results(): void
    {
        for ($i = 0; $i < 205; $i++) {
            $this->pengajuan(status: 'submitted');
        }
        $p = $this->pengajuan('imprestfund', 'submitted', '2026-11-02 08:00:00', attributes: [
            'last_submitted_at' => '2026-11-20 08:00:00',
        ]);
        $this->event($p, 'submit', '2026-11-02 08:00:00');
        $this->event($p, 'verify', '2026-11-06 09:00:00');
        $this->event($p, 'return', '2026-11-06 10:00:00');
        $this->event($p, 'submit', '2026-11-20 08:00:00', 2);

        $rows = $this->csvRows($this->get(route('pencairan.sla-report.export'))->assertOk()->streamedContent());
        $this->assertCount(206, $rows);
        $this->assertSame('REPORT-205', $rows[0]['Nomor Pengajuan']);
        $this->assertSame('REPORT-1', $rows[204]['Nomor Pengajuan']);
        $this->assertSame('REPORT-206', $rows[205]['Nomor Pengajuan']);
        $this->assertSame('4', $rows[205]['Durasi Verifikasi (hari kerja)']);
        $this->assertSame('Melewati SLA', $rows[205]['Hasil Verifikasi']);
        $this->assertSame('Masih berjalan', $rows[205]['Proses Verifikasi Aktif']);
        $this->assertSame('2', $rows[205]['Target Verifikasi Aktif (hari kerja)']);
    }

    public function test_weekend_finish_after_deadline_is_late_without_inventing_a_business_day(): void
    {
        $p = $this->pengajuan('imprestfund', submitted: '2026-11-11 08:00:00');
        $this->event($p, 'submit', '2026-11-11 08:00:00');
        $this->event($p, 'verify', '2026-11-11 09:00:00');
        $this->event($p, 'complete', '2026-11-14 08:00:00');
        $row = $this->rows()->sole();
        $transaction = $row['stages']['transaksi_imprestfund'];
        $this->assertSame('2026-11-13', $transaction['due_date']->toDateString());
        $this->assertSame(2, $transaction['duration_days']);
        $this->assertSame(0, $transaction['overdue_days']);
        $this->assertSame('terlambat', $transaction['result']);
        $this->assertSame(0, $row['stages']['verifikasi']['duration_days']);
    }

    public function test_detail_displays_zero_day_on_time_stages_as_one_without_changing_sla_results(): void
    {
        $p = $this->pengajuan('imprestfund', submitted: '2026-11-13 08:00:00');
        $this->event($p, 'submit', '2026-11-13 08:00:00');
        $this->event($p, 'verify', '2026-11-13 09:00:00');
        $this->event($p, 'complete', '2026-11-15 10:00:00');

        $response = $this->get(route('pencairan.sla-report', ['tab' => 'detail']))->assertOk()
            ->assertSee('durasi tahap 0 hari kerja yang tepat waktu ditampilkan sebagai 1 hari kerja')
            ->assertDontSee('<div class="text-nowrap">0 hari kerja</div>', false);
        $this->assertSame(2, substr_count($response->getContent(), '<div class="text-nowrap">1 hari kerja</div>'));
        $row = $response->viewData('items')->getCollection()->sole();
        foreach (['verifikasi', 'transaksi_imprestfund'] as $stage) {
            $this->assertSame(0, $row['stages'][$stage]['duration_days']);
            $this->assertSame('tepat_waktu', $row['stages'][$stage]['result']);
        }
        $this->assertSame('2026-11-17', $row['stages']['verifikasi']['due_date']->toDateString());
        $this->assertSame('2026-11-17', $row['stages']['transaksi_imprestfund']['due_date']->toDateString());
        $overview = $this->get(route('pencairan.sla-report'))->assertOk();
        $this->assertSame(['total' => 1, 'tepat_waktu' => 1, 'terlambat' => 0, 'undetermined' => 0], $overview->viewData('summary'));
        $this->assertSame(100.0, $overview->viewData('stageSummary')['verifikasi']['compliance']);
        $this->assertSame(100.0, $overview->viewData('stageSummary')['transaksi_imprestfund']['compliance']);
    }

    public function test_duration_display_only_adjusts_zero_day_on_time_results(): void
    {
        $view = 'main.pencairan.partials.sla-report-duration';
        foreach ([1, 2] as $days) {
            $this->view($view, [
                'measurement' => ['duration_days' => $days, 'result' => 'tepat_waktu', 'problem' => null],
                'active' => null,
            ])->assertSee($days.' hari kerja');
        }
        $this->view($view, [
            'measurement' => ['duration_days' => 0, 'result' => 'terlambat', 'problem' => null],
            'active' => null,
        ])->assertSee('0 hari kerja')->assertSee('Melewati SLA');
        $this->view($view, [
            'measurement' => ['duration_days' => null, 'result' => 'data_missing', 'problem' => 'Riwayat tidak tersedia.'],
            'active' => null,
        ])->assertSee('—')->assertDontSee('1 hari kerja');
        $this->view($view, ['measurement' => null, 'active' => null])
            ->assertSee('—')->assertDontSee('1 hari kerja');
    }

    public function test_scheduled_targets_follow_stage_start_for_both_sla_pages(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', config('app.timezone')));
        $this->post(route('settings.sla.store'), [
            'effective_from' => '2026-11-16',
            'verifikasi' => 3, 'transaksi_imprestfund' => 4, 'transaksi_beban' => 6, 'transaksi_honor' => 7,
        ])->assertRedirect(route('settings.sla.index'));

        $this->travelTo(CarbonImmutable::parse('2026-11-24 13:30:00', config('app.timezone')));
        $completed = $this->pengajuan('imprestfund');
        $this->event($completed, 'submit', '2026-11-12 08:00:00');
        $this->event($completed, 'verify', '2026-11-17 09:00:00');
        $this->event($completed, 'complete', '2026-11-20 09:00:00');
        $before = $this->pengajuan('honor', 'verified', attributes: ['verified_at' => '2026-11-15 23:59:59']);
        $after = $this->pengajuan('honor', 'verified', attributes: ['verified_at' => '2026-11-16 00:00:00']);
        $active = $this->pengajuan('beban', 'submitted', '2026-11-16 00:00:00');

        $detail = $this->rows()->keyBy('pengajuan.id');
        $verification = $detail[$completed->id]['stages']['verifikasi'];
        $transaction = $detail[$completed->id]['stages']['transaksi_imprestfund'];
        $this->assertSame(2, $verification['target_days']);
        $this->assertSame('terlambat', $verification['result']);
        $this->assertSame(4, $transaction['target_days']);
        $this->assertSame('tepat_waktu', $transaction['result']);
        $this->assertSame(3, $transaction['duration_days']);
        $this->assertSame(7, $detail[$after->id]['active']['target_days']);
        $overview = $this->get(route('pencairan.sla-report'))->assertOk()->viewData('stageSummary');
        $this->assertSame([2, 3], array_keys($overview['verifikasi']['targets']));
        $this->assertSame([5, 7], array_keys($overview['transaksi_honor']['targets']));

        $monitoring = $this->get(route('pencairan.sla'))->assertOk()->viewData('items')->getCollection()->keyBy('id');
        $this->assertSame(5, $monitoring[$before->id]->sla_measurement['target_days']);
        $this->assertSame('2026-11-20', $monitoring[$before->id]->sla_measurement['due_date']->toDateString());
        $this->assertSame(7, $monitoring[$after->id]->sla_measurement['target_days']);
        $this->assertSame('2026-11-25', $monitoring[$after->id]->sla_measurement['due_date']->toDateString());
        $this->assertSame(3, $monitoring[$active->id]->sla_measurement['target_days']);
    }

    public function test_invalid_filters_are_reported(): void
    {
        foreach ([
            ['tab' => 'unknown'], ['jenis' => 'unknown'], ['start_date' => '2026-02-30'],
            ['start_date' => '2026-11-18', 'end_date' => '2026-11-17'],
            ['per_page' => 0], ['page' => -1], ['pp' => ['SIF']], ['q' => str_repeat('x', 101)],
        ] as $filters) {
            $this->getJson(route('pencairan.sla-report', $filters))->assertUnprocessable();
        }
    }
}
