<?php

namespace Tests\Feature;

use App\Http\Controllers\PencairanWorkflowController;
use App\Models\PencairanAttachment;
use App\Models\PencairanHistory;
use App\Models\PencairanPengajuan;
use App\Models\PencairanRevision;
use App\Services\GoogleSheetService;
use App\Services\PencairanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PencairanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $options;
    private bool $offline = false;
    private array $lookups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        config(['pencairan.actor_source_no_stable' => false, 'pencairan.attachment_disk' => 'local']);
        Storage::fake('local');
        Route::middleware('web')->group(function () {
            Route::post('/_workflow', [PencairanWorkflowController::class, 'store']);
            Route::patch('/_workflow/{pengajuan}', [PencairanWorkflowController::class, 'update']);
            foreach (['submit', 'verify', 'cancel', 'complete', 'returnToUser'] as $action) {
                Route::post('/_workflow/{pengajuan}/'.$action, [PencairanWorkflowController::class, $action]);
            }
            Route::get('/_attachments/{attachment}', [PencairanWorkflowController::class, 'attachment']);
        });
        $this->options = [['id' => str_repeat('a', 64), 'kode_pp' => 'PP01', 'nama_pp' => 'PP Satu',
            'drk_tup' => 'DRK1', 'kode_akun' => '001', 'nama_akun' => 'Akun', 'kode_drk' => 'SIM1',
            'tipe' => 'Operasional', 'uraian_drk' => 'Kegiatan',
            'saldo_awal' => '10.000,00', 'anggaran_terpakai' => '0,00', 'saldo_terkini' => '10.000,00']];
        $this->mock(GoogleSheetService::class, function ($mock) {
            $mock->shouldReceive('getPencairanDrkOptions')->andReturnUsing(function ($pp, $tw, $year) {
                $this->lookups[] = [$pp, $tw, $year];
                if ($this->offline) { throw new \RuntimeException('secret credential error'); }
                return $this->options;
            });
            $mock->shouldReceive('getUnitsFromSheet')->andReturn([]);
        });
        $this->login();
    }

    private function login(string $role = 'user', string $pp = 'PP01', string $username = 'user1', string $no = '7'): void
    {
        $this->withSession(['user_authenticated' => true, 'user_role' => $role,
            'user_data' => ['no' => $no, 'username' => $username, 'password' => 'never-snapshot', 'role' => $role, 'kode_pp' => $pp, 'nama_pp' => 'PP Satu']]);
    }

    private function row(string $jenis): array
    {
        return match ($jenis) {
            'impresfund' => ['no' => '001', 'uraian' => 'Belanja', 'volume' => '2', 'tarif' => '50', 'subtotal' => ''],
            'beban' => ['no' => '001', 'nominal' => '100', 'pajak' => '10', 'neto' => '77', 'bank' => 'Bank', 'nama' => 'Nama', 'rekening' => '000123'],
            'honor' => ['no' => '001', 'nominal' => '100', 'pajak' => '10', 'neto' => '77', 'bank' => 'Bank', 'nama' => 'Nama', 'rekening' => '000123', 'kewarganegaraan' => 'wni', 'penerima' => 'Penerima', 'identitas' => '000098', 'npwp' => '000012'],
        };
    }

    private function draft(string $jenis = 'beban', ?array $rows = null): PencairanPengajuan
    {
        $response = $this->postJson('/_workflow', ['jenis' => $jenis, 'drk_id' => str_repeat('a', 64), 'uraian_pengajuan' => 'Narasi', 'details' => $rows ?? [$this->row($jenis)]]);
        $response->assertCreated()->assertJsonStructure(['id', 'lock_version', 'redirect_url']);
        return PencairanPengajuan::findOrFail($response->json('id'));
    }

    private function action(PencairanPengajuan $p, string $action, array $extra = [])
    {
        return $this->postJson('/_workflow/'.$p->id.'/'.$action, $extra + ['confirmed' => true, 'lock_version' => $p->fresh()->lock_version, 'idempotency_key' => 'key-'.$action.'-'.$p->fresh()->lock_version]);
    }

    private function submitted(string $jenis = 'beban'): PencairanPengajuan
    {
        $p = $this->draft($jenis); $this->action($p, 'submit')->assertOk(); return $p->fresh();
    }

    private function verified(string $jenis = 'beban'): PencairanPengajuan
    {
        $p = $this->submitted($jenis); $this->login('admin', '', 'admin');
        $this->action($p, 'verify')->assertOk(); return $p->fresh();
    }

    public static function kinds(): array { return [['impresfund'], ['beban'], ['honor']]; }

    public function test_detail_displays_effective_subtotal_without_changing_automatic_edit_values(): void
    {
        $p = $this->draft('impresfund', [
            ['no'=>'1', 'uraian'=>'Otomatis', 'volume'=>'2', 'tarif'=>'123,45', 'subtotal'=>''],
            ['no'=>'2', 'uraian'=>'Manual', 'volume'=>'2', 'tarif'=>'100', 'subtotal'=>'175'],
            ['no'=>'3', 'uraian'=>'Manual nol', 'volume'=>'1', 'tarif'=>'100', 'subtotal'=>'0'],
            ['no'=>'4', 'uraian'=>'Belum lengkap', 'volume'=>'', 'tarif'=>'100', 'subtotal'=>''],
        ]);
        $response = $this->get(route('pencairan.show', $p))->assertOk();
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $cells = $xpath->query('//table[contains(@class,"table-bordered")]/tbody/tr/td[last()]');
        $this->assertSame(['246,90','175,00','0,00','Belum dihitung'], array_map(fn($cell) => trim($cell->textContent), iterator_to_array($cells)));
        $this->assertSame('', $p->detailRows()[0]['subtotal']);
        $this->assertSame('175,00', $p->detailRows()[1]['subtotal']);
    }

    #[DataProvider('kinds')]
    public function test_admin_can_create_edit_submit_and_revise_only_own_pp(string $jenis): void
    {
        $this->login('admin', 'PP01', 'admin-pp');
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee('Tambah Pengajuan');
        $p = $this->draft($jenis);
        $this->assertSame('PP01', $p->kode_pp);
        $this->get(route('pencairan.show', $p))->assertOk()->assertSee('Edit / Submit')->assertSee('Batalkan');
        $this->get(route('pencairan.edit', $p))->assertOk();
        $this->patchJson(route('pencairan.update', $p), ['lock_version' => $p->lock_version, 'drk_id' => str_repeat('a',64), 'uraian_pengajuan' => 'Narasi admin', 'details' => [$this->row($jenis)]])->assertOk();
        $this->postJson(route('pencairan.submit', $p), ['lock_version' => $p->fresh()->lock_version, 'idempotency_key' => 'admin-'.$jenis])->assertOk();
        $number = $p->fresh()->nomor_pengajuan;
        $this->get(route('pencairan.edit', $p))->assertForbidden();
        $this->action($p, 'returnToUser', ['remark' => 'Perbaiki'])->assertOk();
        $this->get(route('pencairan.show', $p))->assertOk()->assertSee('Edit / Submit');
        $this->action($p, 'submit')->assertOk();
        $this->assertSame($number, $p->fresh()->nomor_pengajuan);
        $this->login('admin', 'PP02', 'other-admin');
        $this->get(route('pencairan.show', $p))->assertOk()->assertDontSee('Edit / Submit');
        $this->get(route('pencairan.edit', $p))->assertForbidden();
        $this->patchJson(route('pencairan.update', $p), ['lock_version'=>$p->fresh()->lock_version, 'details'=>[]])->assertForbidden();
        $this->action($p,'submit')->assertForbidden();
        $this->action($p,'verify')->assertOk();
    }

    public function test_admin_own_draft_cancel_and_forged_pp_are_protected(): void
    {
        $this->login('admin', 'PP01', 'admin-pp');
        $this->postJson(route('pencairan.store'), ['jenis'=>'beban','details'=>[],'kode_pp'=>'OTHER'])->assertUnprocessable();
        $p = $this->draft();
        $this->login('admin', 'PP02', 'other-admin');
        $this->action($p, 'cancel', ['remark'=>'Tidak berhak draft asing'])->assertStatus(409);
        $this->login('admin', 'PP01', 'admin-pp');
        $this->action($p, 'cancel', ['remark'=>'Batal draft sendiri'])->assertOk();
        $this->assertSame('cancelled', $p->fresh()->status);
        $this->login('admin', '', 'admin');
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertDontSee('Tambah Pengajuan');
        $this->postJson(route('pencairan.store'), ['jenis'=>'beban','details'=>[]])->assertForbidden();
    }

    public function test_initial_draft_with_selected_drk_can_be_saved_during_source_outage(): void
    {
        $this->offline = true;
        $p = $this->draft();
        $this->assertSame(str_repeat('a', 64), $p->drk_source_id);
        $this->assertNull($p->header_snapshot);
        $this->action($p, 'submit')->assertStatus(503);
        $this->assertSame('draft', $p->fresh()->status);
        $this->assertNull($p->fresh()->nomor_pengajuan);
    }

    public function test_real_routes_render_forms_lists_detail_revision_and_admin_actions(): void
    {
        foreach (['impresfund', 'beban', 'honor'] as $kind) {
            $this->login();
            $this->get(route('pencairan.form', $kind))->assertOk()->assertSee('pencairan-submit.js');
            $result = $this->postJson(route('pencairan.store'), ['jenis' => $kind, 'drk_id' => str_repeat('a',64), 'uraian_pengajuan' => 'Narasi pengajuan', 'details' => [$this->row($kind)]]);
            $result->assertCreated();
            $p = PencairanPengajuan::findOrFail($result->json('id'));
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Edit / Submit');
            $this->get(route('pencairan.edit',$p))->assertOk()->assertSee($kind === 'impresfund' ? 'Belanja' : '000123', false);
            $this->postJson(route('pencairan.submit',$p), ['lock_version'=>$p->lock_version, 'idempotency_key'=>'ui-'.$kind])->assertOk();
            $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Revisi 1');
            $this->login('admin','','admin');
            $this->get(route('pencairan.verifikasi'))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Batalkan')->assertSee('Verifikasi');
            $this->postJson(route('pencairan.verify',$p), ['lock_version'=>$p->fresh()->lock_version,'confirmed'=>true])->assertOk();
            $this->get(route('pencairan.transaksi',$kind))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Tanggal Penyelesaian');
            $this->post(route('pencairan.complete',$p), ['lock_version'=>$p->fresh()->lock_version,'confirmed'=>true,'idempotency_key'=>'complete-ui-'.$kind,'completion_date'=>now()->toDateString()])->assertRedirect();
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee($p->fresh()->statusLabel());
            $this->login('user','OTHER','other');
            $this->get(route('pencairan.show',$p))->assertForbidden();
        }
    }

    #[DataProvider('kinds')]
    public function test_all_forms_save_incomplete_drafts_and_roundtrip_manual_values(string $jenis): void
    {
        $row = $this->row($jenis);
        if ($jenis === 'impresfund') { $row['volume'] = ''; $row['subtotal'] = '0'; }
        else { $row['bank'] = ''; }
        $p = $this->draft($jenis, [$row, ['no' => '2', 'pajak' => '0']]);
        $this->assertNull($p->nomor_pengajuan);
        $this->assertSame('draft', $p->status);
        $this->assertSame(0, $p->revisions()->count());
        $this->assertCount(1, $p->detailRows());
        $this->assertSame('001', $p->detailRows()[0]['no']);
        if ($jenis === 'impresfund') {
            $this->assertSame('0.00', $p->details->first()->subtotal_manual);
            $this->assertNull($p->details->first()->volume);
        } else {
            $this->assertSame('000123', $p->detailRows()[0]['rekening']);
            $this->assertSame('77.00', $p->total_neto);
        }
        $this->action($p, 'submit')->assertUnprocessable();
    }

    #[DataProvider('kinds')]
    public function test_complete_workflow_labels_and_no_external_reference_required(string $jenis): void
    {
        $p = $this->verified($jenis);
        $this->action($p, 'complete')->assertUnprocessable()->assertJsonValidationErrors('completion_date');
        $this->action($p, 'complete', ['completion_date' => '2026-10-31'])->assertOk();
        $p->refresh();
        $this->assertSame('completed', $p->status);
        $this->assertSame($jenis === 'impresfund' ? 'Dicairkan' : 'Diajukan ke TUB', $p->statusLabel());
        $this->assertSame('2026-10-31', $p->completion->completion_date->toDateString());
        $this->assertSame('000001/2026/3110/PP01', $p->nomor_pengajuan);
        $this->assertCount(1, $p->revisions);
        $this->assertCount(4, $p->histories);
        $this->assertStringNotContainsString('never-snapshot', json_encode($p->revisions->toArray()));
        $this->assertSame('001', $p->revisions->first()->snapshot['budget']['kode_akun']);
        $this->action($p, 'cancel', ['remark' => 'Cancelled'])->assertConflict();
        $this->action($p, 'complete', ['completion_date' => '2026-10-31'])->assertConflict();
    }

    public function test_manual_subtotal_and_exact_rounding_and_zero_override(): void
    {
        $p = $this->draft('impresfund', [
            ['uraian' => 'a', 'volume' => '0,03', 'tarif' => '0,50', 'subtotal' => ''],
            ['uraian' => 'b', 'volume' => '2', 'tarif' => '100', 'subtotal' => '7,23'],
            ['uraian' => 'c', 'volume' => '2', 'tarif' => '100', 'subtotal' => '0'],
        ]);
        $this->assertSame('7.25', $p->total_bruto);
        $this->assertSame('0.02', $p->details[0]->subtotal_efektif);
        $this->assertNull($p->details[0]->subtotal_manual);
        $this->assertSame('', $p->detailRows()[0]['subtotal']);
        $this->action($p, 'submit')->assertOk();
    }

    public function test_draft_without_header_or_rows_is_saved_but_not_submittable(): void
    {
        $response = $this->postJson('/_workflow', ['jenis' => 'honor', 'details' => []])->assertCreated();
        $p = PencairanPengajuan::find($response->json('id'));
        $this->assertNull($p->drk_source_id);
        $this->action($p, 'submit')->assertUnprocessable();
    }

    public static function invalidNumbers(): array
    {
        return [['1.25'], ['-1'], ['1,234'], ['1e3'], ['1,2,3'], ['10.000.000.000,01'], [1.2], [true], [['x']]];
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_numeric_input_is_rejected_even_on_draft(mixed $value): void
    {
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => [['nominal' => $value]]])->assertUnprocessable()->assertJsonValidationErrors('details.0.nominal');
        $this->assertDatabaseCount('pengajuans', 0);
    }

    public function test_row_text_bounds_tax_and_positive_volume(): void
    {
        foreach ([['pajak' => '101', 'nominal' => '100'], ['rekening' => 123], ['bank' => str_repeat('a', 256)]] as $row) {
            $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => [$row]])->assertUnprocessable();
        }
        $this->postJson('/_workflow', ['jenis' => 'impresfund', 'details' => [['volume' => '0']]])->assertUnprocessable();
        $this->postJson('/_workflow', ['jenis' => 'honor', 'details' => [['kewarganegaraan' => 'BAD']]])->assertUnprocessable();
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => array_fill(0, 501, ['no' => '1'])])->assertUnprocessable();
    }

    public function test_maximum_rows_values_and_total_are_exact_and_zero_rows_can_accompany_positive_total(): void
    {
        $row = $this->row('beban'); $row['nominal'] = '10.000.000.000'; $row['neto'] = '10.000.000.000';
        $p = $this->draft('beban', array_fill(0, 500, $row));
        $this->assertSame('5000000000000.00', $p->total_bruto);
        $this->assertCount(500, $p->detailRows());
        $this->assertSame('10.000.000.000', $row['nominal']);
        $zero = $this->row('beban'); $zero['nominal'] = '0'; $zero['pajak'] = '0'; $zero['neto'] = '0';
        $p2 = $this->draft('beban', [$zero, $this->row('beban')]);
        $this->action($p2, 'submit')->assertOk();
        $this->assertSame('100.00', $p2->fresh()->total_bruto);
    }

    public function test_number_expands_beyond_six_digits_and_eager_loaded_details_roundtrip(): void
    {
        DB::table('pengajuan_number_sequences')->insert(['year' => 2026, 'last_number' => 999999]);
        $p = $this->submitted('impresfund');
        $this->assertSame('1000000/2026/3110/PP01', $p->nomor_pengajuan);
        $loaded = PencairanPengajuan::with('impresfundDetails')->find($p->id);
        $this->assertSame($p->detailRows(), $loaded->detailRows());
        $this->assertSame('100.00', $p->revisions->first()->snapshot['detail_values'][0]['subtotal_efektif']);
        $this->assertNull($p->revisions->first()->snapshot['detail_values'][0]['subtotal_manual']);
    }

    public function test_create_detail_failure_rolls_back_draft_and_actor(): void
    {
        \App\Models\PencairanBebanDetail::creating(fn () => throw new \RuntimeException('Draft detail failure'));
        try {
            $this->withoutExceptionHandling(); $this->draft(); $this->fail('Expected failure');
        } catch (\RuntimeException $e) { $this->assertSame('Draft detail failure', $e->getMessage()); }
        finally { \App\Models\PencairanBebanDetail::flushEventListeners(); }
        $this->assertDatabaseCount('pengajuans', 0); $this->assertDatabaseCount('pengajuan_actors', 0);
        $this->assertDatabaseCount('pengajuan_beban_details', 0); $this->assertDatabaseCount('pengajuan_histories', 0);
    }

    public function test_public_disk_is_rejected_and_rolls_back_completion(): void
    {
        $p = $this->verified(); config(['pencairan.attachment_disk' => 'public']);
        $this->action($p, 'complete', ['completion_date' => '2026-10-31', 'attachments' => [UploadedFile::fake()->image('proof.png')]])->assertStatus(503);
        $this->assertSame('verified', $p->fresh()->status);
        $this->assertDatabaseCount('pengajuan_completions', 0);
    }

    public function test_forged_headers_totals_and_pp_are_not_trusted(): void
    {
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => [], 'kode_pp' => 'OTHER'])->assertUnprocessable();
        $response = $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => [$this->row('beban')], 'drk_id' => str_repeat('a', 64),
            'kode_pp' => 'PP01', 'nama_pp' => 'FORGED', 'total_bruto' => '0', 'saldo_terkini' => '999999999', 'tahun_anggaran' => 2025, 'triwulan' => 1, 'created_by_actor_id' => 999, 'status' => 'verified'])->assertCreated();
        $p = PencairanPengajuan::find($response->json('id'));
        $this->assertSame('100.00', $p->total_bruto);
        $this->assertSame(2026, $p->tahun_anggaran); $this->assertSame(4, $p->triwulan);
        $this->assertSame('PP Satu', $p->nama_pp); $this->assertSame('draft', $p->status);
        $this->assertSame('10.000,00', $p->header_snapshot['saldo_terkini']);
    }

    public function test_auth_roles_and_cross_pp_are_enforced_for_every_action(): void
    {
        $p = $this->draft();
        $this->login('user', 'PP02', 'user2');
        foreach (['submit', 'cancel', 'verify', 'returnToUser', 'complete'] as $action) { $this->action($p, $action)->assertForbidden(); }
        $this->patchJson('/_workflow/'.$p->id, ['lock_version' => 0, 'details' => []])->assertForbidden();
        $this->login('admin', '', 'admin');
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => []])->assertForbidden();
        $this->action($p, 'submit')->assertForbidden();
        $this->patchJson('/_workflow/'.$p->id, ['lock_version' => 0, 'details' => []])->assertForbidden();
        $this->withSession(['user_authenticated' => false]);
        $this->action($p, 'cancel')->assertForbidden();
    }

    public function test_saldo_equal_passes_short_negative_missing_invalid_and_offline_fail_closed(): void
    {
        $p = $this->draft();
        foreach (['99,99', '-1,00'] as $balance) {
            $this->options[0]['saldo_terkini'] = $balance;
            $this->action($p, 'submit')->assertUnprocessable()->assertJsonValidationErrors('saldo');
        }
        foreach ([null, '', 'garbage'] as $balance) {
            $this->options[0]['saldo_terkini'] = $balance;
            $this->action($p, 'submit')->assertStatus(503);
        }
        $this->offline = true;
        $this->action($p, 'submit')->assertStatus(503)->assertDontSee('secret credential');
        $this->assertSame('draft', $p->fresh()->status);
        $this->assertDatabaseCount('pengajuan_number_sequences', 0);
        $this->assertDatabaseCount('pengajuan_revisions', 0);
        $this->assertDatabaseCount('pengajuan_histories', 1);
        $this->offline = false; $this->options[0]['saldo_terkini'] = '100,00';
        $this->action($p, 'submit')->assertOk();
        $this->login('admin', '', 'admin'); $this->offline = true;
        $this->action($p, 'verify')->assertStatus(503);
        $this->offline = false; $this->options[0]['saldo_terkini'] = '99,00';
        $this->action($p, 'verify')->assertUnprocessable();
        $this->offline = true;
        $this->action($p, 'returnToUser', ['remark' => 'Saldo gagal tersedia'])->assertOk();
        $this->action($p, 'cancel', ['remark' => 'Batalkan'])->assertOk();
    }

    public function test_sheet_client_initialization_failure_blocks_approval_but_not_return_cancel_or_empty_draft(): void
    {
        $p = $this->submitted();
        $this->app->forgetInstance(GoogleSheetService::class);
        $this->app->bind(GoogleSheetService::class, fn () => throw new \RuntimeException('Missing credentials'));
        $this->login('admin', '', 'admin');
        $this->action($p, 'verify')->assertStatus(503)->assertDontSee('Missing credentials');
        $this->action($p, 'returnToUser', ['remark' => 'Sumber gagal'])->assertOk();
        $this->action($p, 'cancel', ['remark' => 'Sumber tidak tersedia'])->assertOk();
        $this->login();
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => []])->assertCreated();
    }

    public function test_missing_foreign_and_duplicate_drk_block_submission_without_losing_draft(): void
    {
        $p = $this->draft();
        $original = $this->options[0];
        foreach ([[], [array_replace($original, ['kode_pp' => 'OTHER'])], [$original, $original]] as $options) {
            $this->options = $options;
            $this->action($p, 'submit')->assertUnprocessable()->assertJsonValidationErrors('drk_id');
            $this->assertSame('draft', $p->fresh()->status);
        }
        $this->options = [$original];
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => [], 'drk_id' => 'foreign'])->assertUnprocessable();
    }

    public function test_revision_return_from_verified_resets_active_verification_and_keeps_number_and_immutable_audit(): void
    {
        $p = $this->verified(); $number = $p->nomor_pengajuan;
        $snapshot = $p->revisions->first()->snapshot;
        $this->action($p, 'returnToUser', ['remark' => '   '])->assertUnprocessable();
        $this->action($p, 'returnToUser', ['remark' => 'Koreksi rekening'])->assertOk();
        $p->refresh(); $this->assertNull($p->verified_at); $this->assertNull($p->verified_by);
        $this->action($p, 'complete', ['completion_date' => '2026-10-31'])->assertConflict();
        $this->login(); $row = $this->row('beban'); $row['neto'] = '81';
        $this->patchJson('/_workflow/'.$p->id, ['lock_version' => $p->lock_version, 'drk_id' => $p->drk_source_id, 'uraian_pengajuan' => 'Revisi', 'details' => [$row]])->assertOk();
        $this->action($p, 'submit')->assertOk();
        $p->refresh(); $this->assertSame($number, $p->nomor_pengajuan); $this->assertSame(2, $p->revision_no);
        $this->assertSame($snapshot, $p->revisions->first()->snapshot);
        $this->assertSame('81,00', $p->revisions->last()->snapshot['details'][0]['neto']);
        $this->assertDatabaseCount('pengajuan_number_sequences', 1);
        $this->assertSame(1, DB::table('pengajuan_number_sequences')->value('last_number'));
        $this->login('admin', '', 'admin'); $this->action($p, 'verify')->assertOk();
        $this->assertSame(2, $p->histories()->where('action', 'verify')->count());
    }

    public function test_cancel_requires_confirmation_admin_remark_and_keeps_number_no_reuse(): void
    {
        $p = $this->submitted(); $number = $p->nomor_pengajuan;
        $this->action($p, 'cancel')->assertConflict();
        $this->login('admin', '', 'admin');
        $this->action($p, 'cancel', ['remark' => ''])->assertUnprocessable();
        $this->action($p, 'cancel', ['confirmed' => false, 'remark' => 'Cancel'])->assertUnprocessable();
        $this->action($p, 'cancel', ['remark' => 'Dibatalkan admin'])->assertOk();
        $this->assertSame($number, $p->fresh()->nomor_pengajuan);
        $this->action($p, 'cancel', ['remark' => 'Again'])->assertConflict();
        $this->login(); $second = $this->submitted('honor'); $this->assertSame(2, $second->nomor_urut);
        $draft = $this->draft(); $this->action($draft, 'cancel', ['confirmed' => false])->assertUnprocessable();
        $this->action($draft, 'cancel')->assertOk();
        $verified = $this->verified('impresfund');
        $this->action($verified, 'cancel', ['remark' => 'No'])->assertConflict();
    }

    public function test_user_cannot_edit_locked_states_or_change_kind_and_versions_conflict(): void
    {
        $p = $this->draft();
        $this->patchJson('/_workflow/'.$p->id, ['jenis' => 'honor', 'details' => [], 'lock_version' => 0])->assertUnprocessable();
        $payload = ['details' => [$this->row('beban')], 'drk_id' => $p->drk_source_id, 'uraian_pengajuan' => 'Updated', 'lock_version' => 0];
        $this->patchJson('/_workflow/'.$p->id, $payload)->assertOk();
        $this->patchJson('/_workflow/'.$p->id, $payload)->assertConflict();
        $this->action($p, 'submit', ['lock_version' => 0])->assertConflict();
        $this->action($p, 'submit')->assertOk();
        $this->patchJson('/_workflow/'.$p->id, array_replace($payload, ['lock_version' => 2]))->assertConflict();
        $this->login('admin', '', 'admin');
        $this->action($p, 'verify', ['lock_version' => 0])->assertConflict();
        $this->action($p, 'verify')->assertOk(); $this->login();
        $this->patchJson('/_workflow/'.$p->id, array_replace($payload, ['lock_version' => 3]))->assertConflict();
    }

    public function test_submit_idempotency_retries_identical_payload_before_stale_version_checks(): void
    {
        $p = $this->draft(); $payload = ['lock_version' => 0, 'idempotency_key' => 'same'];
        $first = $this->postJson('/_workflow/'.$p->id.'/submit', $payload)->assertOk()->json();
        $this->offline = true;
        $this->postJson('/_workflow/'.$p->id.'/submit', $payload)->assertOk()->assertExactJson($first);
        $this->postJson('/_workflow/'.$p->id.'/submit', $payload + ['confirmed' => true])->assertConflict();
        $this->assertDatabaseCount('pengajuan_revisions', 1); $this->assertDatabaseCount('pengajuan_histories', 2);
        $this->assertDatabaseCount('pengajuan_idempotencies', 1);
    }

    public function test_complete_idempotency_includes_file_content_and_does_not_duplicate_storage(): void
    {
        $p = $this->verified();
        $make = fn ($content) => UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n".$content);
        $payload = ['lock_version' => $p->lock_version, 'confirmed' => true, 'idempotency_key' => 'same', 'completion_date' => '2026-10-31'];
        $first = $this->postJson('/_workflow/'.$p->id.'/complete', $payload + ['attachments' => [$make('a')]])->assertOk()->json();
        $this->postJson('/_workflow/'.$p->id.'/complete', $payload + ['attachments' => [$make('a')]])->assertOk()->assertExactJson($first);
        $this->postJson('/_workflow/'.$p->id.'/complete', $payload + ['attachments' => [$make('b')]])->assertConflict();
        $this->assertDatabaseCount('pengajuan_completions', 1); $this->assertDatabaseCount('pengajuan_attachments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_cross_period_draft_and_returned_block_but_original_submitted_verification_allowed(): void
    {
        $draft = $this->draft(); $submitted = $this->submitted();
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->action($draft, 'submit')->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->login('admin', '', 'admin'); $this->action($submitted, 'verify')->assertOk();
        $this->assertSame(['PP01', 4, 2026], end($this->lookups));
        $this->action($submitted, 'returnToUser', ['remark' => 'Koreksi'])->assertOk();
        $this->login(); $this->action($submitted, 'submit')->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->login('admin', '', 'admin'); $this->action($submitted, 'cancel', ['remark' => 'Lintas periode'])->assertOk();
        $this->login(); $next = $this->submitted();
        $this->assertStringStartsWith('000001/2027/0101', $next->nomor_pengajuan);
        $this->assertDatabaseCount('pengajuan_number_sequences', 2);
        $this->action($draft, 'cancel')->assertOk();
    }

    public function test_quarter_boundary_is_enforced_not_only_year(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)); $p = $this->draft();
        $this->travelTo(now()->setDate(2026, 10, 1));
        $this->action($p, 'submit')->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->assertSame(3, $p->triwulan);
    }

    public function test_completion_dates_follow_latest_active_verification_and_calendar_dates(): void
    {
        $p = $this->verified();
        foreach (['2026-10-30', '2026-11-01', 'bad', '2026-02-30'] as $date) { $this->action($p, 'complete', ['completion_date' => $date])->assertUnprocessable(); }
        $this->travelTo(now()->setDate(2026, 11, 2));
        $this->action($p, 'complete', ['completion_date' => '2026-10-31'])->assertOk();
        $this->assertSame('2026-11-02', $p->fresh()->completed_at->toDateString());
        $this->login(); $p2 = $this->verified();
        $this->action($p2, 'returnToUser', ['remark' => 'Koreksi'])->assertOk();
        $this->travelTo(now()->setDate(2026, 11, 3)); $this->login(); $this->action($p2, 'submit')->assertOk();
        $this->login('admin', '', 'admin'); $this->action($p2, 'verify')->assertOk();
        $this->action($p2, 'complete', ['completion_date' => '2026-11-02'])->assertUnprocessable();
    }

    public function test_attachments_content_size_count_external_fields_and_private_download_authorization(): void
    {
        $p = $this->verified();
        foreach ([['referensi_tub' => 'x'], ['nomor_bukti_pencairan' => 'x'], ['tub_document_number' => 'x']] as $extra) {
            $this->action($p, 'complete', $extra + ['completion_date' => '2026-10-31'])->assertUnprocessable();
        }
        foreach ([
            [UploadedFile::fake()->createWithContent('fake.pdf', 'plain malicious text')],
            [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
            array_fill(0, 6, UploadedFile::fake()->image('proof.png')),
        ] as $files) {
            $this->action($p, 'complete', ['completion_date' => '2026-10-31', 'attachments' => $files])->assertUnprocessable();
        }
        $this->action($p, 'complete', ['completion_date' => '2026-10-31', 'attachments' => [UploadedFile::fake()->image('proof.png')]])->assertOk();
        $attachment = PencairanAttachment::first(); $this->assertTrue(Storage::disk('local')->exists($attachment->path));
        $this->assertSame('image/png', $attachment->mime);
        $this->get('/_attachments/'.$attachment->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->login(); $this->get('/_attachments/'.$attachment->id)->assertOk();
        $this->login('user', 'OTHER', 'other'); $this->get('/_attachments/'.$attachment->id)->assertForbidden();
        $this->withSession(['user_authenticated' => false]); $this->get('/_attachments/'.$attachment->id)->assertForbidden();
    }

    public function test_transaction_failure_rolls_back_number_revision_and_history(): void
    {
        $p = $this->draft();
        PencairanRevision::creating(fn () => throw new \RuntimeException('Injected failure'));
        try { $this->withoutExceptionHandling(); $this->action($p, 'submit'); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Injected failure', $e->getMessage()); }
        finally { PencairanRevision::flushEventListeners(); }
        $this->assertSame('draft', $p->fresh()->status); $this->assertSame(0, $p->fresh()->lock_version);
        $this->assertDatabaseCount('pengajuan_number_sequences', 0); $this->assertDatabaseCount('pengajuan_revisions', 0);
        $this->assertDatabaseCount('pengajuan_histories', 1); $this->assertDatabaseCount('pengajuan_idempotencies', 0);
    }

    public function test_upload_is_compensated_when_db_fails(): void
    {
        $p = $this->verified();
        PencairanAttachment::creating(fn () => throw new \RuntimeException('Attachment DB failure'));
        try {
            $this->withoutExceptionHandling();
            $this->action($p, 'complete', ['completion_date' => '2026-10-31', 'attachments' => [UploadedFile::fake()->image('proof.png')]]);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) { $this->assertSame('Attachment DB failure', $e->getMessage()); }
        finally { PencairanAttachment::flushEventListeners(); }
        $this->assertSame('verified', $p->fresh()->status);
        $this->assertDatabaseCount('pengajuan_completions', 0); $this->assertDatabaseCount('pengajuan_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_native_form_redirect_flash_and_local_actor_stability(): void
    {
        $this->post('/_workflow', ['jenis' => 'beban', 'details' => []])->assertRedirect('/pencairan/pengajuan/1')->assertSessionHas('success');
        $this->login('user', 'PP01', 'user1', '99'); $p = $this->draft();
        $this->assertDatabaseCount('pengajuan_actors', 1);
        $this->post('/_workflow/'.$p->id.'/cancel', ['confirmed' => '1', 'lock_version' => 0])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('user1', $p->histories()->first()->actor_name);
    }

    public function test_stable_source_no_preserves_actor_when_username_changes(): void
    {
        config(['pencairan.actor_source_no_stable' => true]);
        $p = $this->draft(); $this->login('user', 'PP01', 'renamed', '7');
        $this->action($p, 'cancel')->assertOk();
        $this->assertDatabaseCount('pengajuan_actors', 1);
        $this->assertSame('user1', $p->histories()->first()->actor_name);
        $this->assertSame('renamed', $p->histories()->reorder('id', 'desc')->first()->actor_name);
    }

    public function test_audit_models_are_append_only_and_pp_scope_does_not_leak(): void
    {
        $p = $this->submitted();
        $request = request(); $request->setLaravelSession(session()->driver());
        $actor = app(PencairanService::class)->actor($request);
        $this->assertSame(1, PencairanPengajuan::forActor($actor)->count());
        $this->assertSame(0, PencairanPengajuan::forActor(array_replace($actor, ['kode_pp' => 'OTHER']))->count());
        $revision = $p->revisions->first();
        $this->expectException(\LogicException::class); $revision->update(['snapshot' => []]);
    }
}