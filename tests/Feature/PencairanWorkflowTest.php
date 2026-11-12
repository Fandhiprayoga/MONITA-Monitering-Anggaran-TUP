<?php

namespace Tests\Feature;

use App\Http\Controllers\PencairanWorkflowController;
use App\Models\Bank;
use App\Models\PencairanAttachment;
use App\Models\JenisPenghasilan;
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
use Illuminate\Validation\ValidationException;
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
        Bank::create(['kode_bank' => '002', 'inisial' => 'BRI', 'nama_bank' => 'Bank Rakyat Indonesia', 'status' => 'aktif']);
        JenisPenghasilan::create(['kode_jenis' => '01', 'nama_jenis_pph' => 'Honorarium', 'tarif_pph' => '5.00']);
        JenisPenghasilan::create(['kode_jenis' => 'PPH-21', 'nama_jenis_pph' => 'PPh Pasal 21', 'tarif_pph' => '5.00']);
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
        $this->options = [['id' => str_repeat('a', 64), 'kode_pp' => 'PP01', 'kode_pp_sheet' => '8910', 'nama_pp' => 'PP Satu',
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
            $mock->shouldReceive('getPencairanUnitsFromSheet')->andReturn(['PP01' => 'PP Satu', 'PP02' => 'PP Dua']);
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
            'beban' => ['no' => '001', 'nominal' => '100', 'pajak' => '10', 'neto' => '77', 'bank' => 'BRI', 'nama' => 'Nama', 'rekening' => '000123'],
            'honor' => ['no' => '001', 'nominal' => '100', 'pajak' => '10', 'neto' => '77', 'bank' => 'BRI', 'nama' => 'Nama', 'rekening' => '000123', 'kode_jenis' => 'PPH-21', 'penerima' => 'Penerima', 'identitas' => '000098', 'npwp' => '000012'],
        };
    }

    private function draft(string $jenis = 'beban', ?array $rows = null): PencairanPengajuan
    {
        $response = $this->postJson('/_workflow', ['jenis' => $jenis, 'drk_id' => str_repeat('a', 64), 'uraian_pengajuan' => 'Narasi', 'details' => $rows ?? [$this->row($jenis)]]);
        $response->assertCreated()->assertJsonStructure(['uuid', 'lock_version', 'redirect_url']);
        return PencairanPengajuan::where('uuid', $response->json('uuid'))->firstOrFail();
    }

    private function action(PencairanPengajuan $p, string $action, array $extra = [])
    {
        return $this->postJson('/_workflow/'.$p->uuid.'/'.$action, $extra + ['confirmed' => true, 'lock_version' => $p->fresh()->lock_version, 'idempotency_key' => 'key-'.$action.'-'.$p->fresh()->lock_version]);
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
    public static function readonlySimkugKinds(): array { return [['beban'], ['honor']]; }

    public function test_detail_displays_effective_subtotal_without_changing_automatic_edit_values(): void
    {
        $p = $this->draft('impresfund', [
            ['no'=>'1', 'uraian'=>'Otomatis', 'volume'=>'2', 'tarif'=>'123,45', 'subtotal'=>''],
            ['no'=>'2', 'uraian'=>'Manual', 'volume'=>'2', 'tarif'=>'1000000', 'subtotal'=>'1234567,89'],
            ['no'=>'3', 'uraian'=>'Manual nol', 'volume'=>'1', 'tarif'=>'100', 'subtotal'=>'0'],
            ['no'=>'4', 'uraian'=>'Belum lengkap', 'volume'=>'', 'tarif'=>'100', 'subtotal'=>''],
        ]);
        $legacySnapshot = $p->header_snapshot;
        unset($legacySnapshot['kode_pp_sheet']);
        $legacySnapshot['saldo_awal'] = '36135000,00';
        $legacySnapshot['anggaran_terpakai'] = '0,00';
        $legacySnapshot['saldo_terkini'] = '36135000,00';
        $p->header_snapshot = $legacySnapshot;
        $p->save();
        $detailUrl = route('pencairan.show', $p);
        $this->assertStringEndsWith('/'.$p->uuid, $detailUrl);
        $this->get('/pencairan/pengajuan/'.$p->id)->assertNotFound();
        $response = $this->get($detailUrl)->assertOk()
            ->assertSee('>Detail</button>', false)->assertSee('>Riwayat</button>', false)
            ->assertSee('role="tablist"', false)->assertSee('role="tabpanel"', false)
            ->assertSee('pengajuan-riwayat-'.$p->id, false)
            ->assertSee('Kode pp')->assertSee('8910')->assertDontSee('<div>PP01</div>', false)
            ->assertSee('spreadsheet-grid', false)->assertSee('spreadsheet-viewport', false)
            ->assertSee('aria-label="Rincian pengajuan"', false)
            ->assertSee('data-grid-select-all', false)->assertSee('data-grid-select-column="0"', false)
            ->assertSee('data-grid-select-row', false)->assertSee('data-grid-cell', false)
            ->assertSee('pencairan-detail-grid.js');
        $response->assertSee('workflow-timeline', false)
            ->assertSee('Pengajuan dibuat')->assertSee('Draft')->assertSee('Terbaru');
        $response->assertSee('Uraian Pengajuan')
            ->assertSee('Narasi');
        $response->assertSee('Nominal: Rp')->assertDontSee('Pajak: Rp')->assertDontSee('Neto: Rp');
        $response->assertSee('<div>36.135.000</div>', false)->assertSee('<div>0</div>', false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $cells = $xpath->query('//table[contains(@class,"table-bordered")]/tbody/tr/td[last()]');
        $this->assertSame(['246,90','1.234.567,89','0','Belum dihitung'], array_map(fn($cell) => trim($cell->textContent), iterator_to_array($cells)));
        $this->assertSame('', $p->detailRows()[0]['subtotal']);
        $this->assertSame('1234567,89', $p->detailRows()[1]['subtotal']);
    }

    #[DataProvider('readonlySimkugKinds')]
    public function test_simkug_shows_same_details_and_copyable_readonly_headers_when_verified(string $jenis): void
    {
        $p = $this->verified($jenis);
        $p->uraian_pengajuan = "Narasi \"SIMKUG\" & <uji>\nBaris kedua";
        $p->save();
        $response = $this->get(route('pencairan.show', $p))->assertOk()
            ->assertSee('>SIMKUG</button>', false)
            ->assertSee('pencairan-simkug.js');
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $panel = '//section[@data-simkug-panel]';
        $this->assertSame(1, $xpath->query($panel)->length);
        $fields = $xpath->query($panel.'//input | '.$panel.'//textarea');
        $this->assertSame($fields->length, $xpath->query($panel.'//button[@data-simkug-copy]')->length);
        $values = [];
        foreach ($fields as $field) {
            $this->assertTrue($field->hasAttribute('readonly'));
            $id = $field->getAttribute('id');
            $label = $xpath->query($panel.'//label[@for="'.$id.'"]')->item(0)->textContent;
            $values[$label] = $field->tagName === 'textarea' ? $field->textContent : $field->getAttribute('value');
        }
        $this->assertSame('001', $values['Kode akun']);
        $this->assertSame('8910', $values['Kode pp']);
        $this->assertSame('SIM1', $values['Kode drk']);
        $this->assertSame($p->uraian_pengajuan, $values['Uraian pengajuan']);
        $this->assertSame('2026', $values['Tahun anggaran']);
        $this->assertSame('4', $values['Triwulan']);
        $expectedNominal = rtrim(rtrim(number_format((float) $p->total_bruto, 2, ',', '.'), '0'), ',');
        $this->assertSame($expectedNominal, $values['Nominal']);
        foreach (['Id', 'Checked at', 'Saldo terkini decimal', 'Kode pp sheet'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $values);
        }
        $detailCells = $xpath->query('//section[@id="pengajuan-detail-'.$p->id.'"]//td[@data-grid-cell]');
        $simkugCells = $xpath->query($panel.'//td[@data-grid-cell]');
        $cellValues = fn ($cells) => array_map(fn ($cell) => trim($cell->textContent), iterator_to_array($cells));
        $this->assertNotEmpty($cellValues($detailCells));
        $this->assertSame($cellValues($detailCells), $cellValues($simkugCells));
        $this->assertSame(1, $xpath->query($panel.'//*[@data-simkug-copy-status][@role="status"]')->length);
        $this->assertSame(0, $xpath->query($panel.'//form')->length);
    }

    private function simkugPayload(): array
    {
        return [
            'kode_akun' => '000123', 'nama_akun' => 'Akun SIMKUG', 'dc' => 'C',
            'keterangan' => "Narasi \"SIMKUG\" & <uji>\nBaris kedua", 'nilai' => '1.234,56',
            'kode_pp' => '0099', 'nama_pp' => 'PP SIMKUG', 'kode_drk' => '000DRK', 'nama_drk' => 'DRK SIMKUG',
        ];
    }

    public function test_impresfund_simkug_initializes_one_total_row_and_saves_all_columns_without_changing_submission(): void
    {
        $p = $this->verified('impresfund');
        $before = $p->only(['total_bruto', 'total_pajak', 'total_neto', 'header_snapshot', 'uraian_pengajuan', 'kode_pp', 'revision_no']);
        $details = $p->detailRows();
        $response = $this->get(route('pencairan.show', $p))->assertOk()->assertSee('Simpan Data SIMKUG')
            ->assertDontSee('Salin baris ke SIMKUG')->assertDontSee('data-simkug-row', false)
            ->assertSee('data-editable="true"', false)
            ->assertSee('data-simkug-field readonly required', false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $grid = '//table[@aria-label="Data SIMKUG Impresfund"]';
        $this->assertSame(1, $xpath->query($grid.'[@role="grid"]')->length);
        $this->assertSame(1, $xpath->query($grid.'//tr[@data-grid-row]')->length);
        $this->assertSame(9, $xpath->query($grid.'//td[@data-grid-cell]')->length);
        $this->assertSame(9, $xpath->query($grid.'//button[@data-grid-select-column]')->length);
        $this->assertSame(1, $xpath->query($grid.'//button[@data-grid-select-all]')->length);
        $this->assertSame(1, $xpath->query($grid.'//button[@data-grid-select-row]')->length);
        $data = $response->viewData('simkugData');
        $this->assertSame(['kode_akun', 'nama_akun', 'dc', 'keterangan', 'nilai', 'kode_pp', 'nama_pp', 'kode_drk', 'nama_drk'], array_keys($data));
        $this->assertSame('001', $data['kode_akun']);
        $this->assertSame('8910', $data['kode_pp']);
        $this->assertSame('SIM1', $data['kode_drk']);
        $this->assertSame('Kegiatan', $data['nama_drk']);
        $this->assertSame('100,00', $data['nilai']);
        $this->assertSame('Narasi', $data['keterangan']);
        $this->assertSame('', $data['dc']);
        $this->assertNull($p->simkug_data);
        $url = route('pencairan.simkug.save', $p);
        $this->patchJson($url, ['lock_version' => $p->lock_version, 'simkug' => $this->simkugPayload()])
            ->assertOk()->assertJsonPath('lock_version', $p->lock_version + 1);
        $saved = $p->fresh();
        $this->assertSame(array_replace($this->simkugPayload(), ['nilai' => '1234,56']), $saved->simkug_data);
        $this->assertSame('1234,56', $saved->simkug_data['nilai']);
        $this->assertSame($before, $saved->only(array_keys($before)));
        $this->assertSame($details, $saved->detailRows());
        $history = $saved->histories()->where('action', 'simkug')->firstOrFail();
        $this->assertSame('admin', $history->actor_role);
        $this->assertSame($saved->simkug_data, json_decode($history->remark, true)['sesudah']);
        $this->get(route('pencairan.show', ['pengajuan' => $p, 'menu' => 'transaksi', 'tab' => 'simkug']))
            ->assertOk()->assertViewHas('simkugData', $saved->simkug_data)
            ->assertSee('value="000123"', false)->assertDontSee('<uji>', false);
        $this->patchJson($url, ['lock_version' => $p->lock_version, 'simkug' => $this->simkugPayload()])
            ->assertStatus(409);
    }

    public function test_simkug_rejects_invalid_fields_numbers_and_unknown_columns(): void
    {
        $p = $this->verified('impresfund');
        foreach ([
            ['dc' => 'X'], ['dc' => ''], ['nilai' => '-1'], ['nilai' => '1.2345'],
            ['nilai' => '5.000.000.000.000,01'], ['kode_akun' => '   '],
            ['nama_akun' => str_repeat('a', 256)], ['kode_pp' => ['invalid']],
            ['extra' => 'not allowed'],
        ] as $invalid) {
            $this->patchJson(route('pencairan.simkug.save', $p), [
                'lock_version' => $p->lock_version, 'simkug' => array_replace($this->simkugPayload(), $invalid),
            ])->assertUnprocessable()->assertJsonValidationErrors(isset($invalid['extra']) ? 'simkug' : 'simkug.'.array_key_first($invalid));
        }
        $this->assertNull($p->fresh()->simkug_data);
        $this->assertSame($p->lock_version, $p->fresh()->lock_version);
        foreach (['D', 'C'] as $dc) {
            $current = $p->fresh();
            $this->patchJson(route('pencairan.simkug.save', $p), [
                'lock_version' => $current->lock_version,
                'simkug' => array_replace($this->simkugPayload(), ['dc' => $dc, 'nilai' => '5.000.000.000.000,00']),
            ])->assertOk();
            $this->assertSame($dc, $p->fresh()->simkug_data['dc']);
        }
    }

    public function test_simkug_is_admin_only_verified_impresfund_and_completed_data_remains_readonly(): void
    {
        $p = $this->verified('impresfund');
        $url = route('pencairan.simkug.save', $p);
        $payload = ['lock_version' => $p->lock_version, 'simkug' => $this->simkugPayload()];
        $this->login();
        $this->patchJson($url, $payload)->assertForbidden();
        $this->get(route('pencairan.show', $p))->assertOk()->assertDontSee('Simpan Data SIMKUG');
        $this->login('user', 'OTHER');
        $this->patchJson($url, $payload)->assertForbidden();
        $this->login('admin', '', 'admin');
        $this->patchJson($url, $payload)->assertOk();
        $this->action($p, 'complete', ['completion_date' => now()->toDateString()])->assertOk();
        $this->get(route('pencairan.show', $p))->assertOk()->assertSee('>SIMKUG</button>', false)
            ->assertSee('value="000123"', false)->assertDontSee('Simpan Data SIMKUG');
        $this->patchJson($url, array_replace($payload, ['lock_version' => $p->fresh()->lock_version]))->assertStatus(409);
        foreach (['draft', 'submitted', 'returned', 'cancelled'] as $status) {
            $p->status = $status; $p->save();
            $this->patchJson($url, array_replace($payload, ['lock_version' => $p->fresh()->lock_version]))->assertStatus(409);
        }
        foreach (['beban', 'honor'] as $jenis) {
            $this->login();
            $other = $this->verified($jenis);
            $this->patchJson(route('pencairan.simkug.save', $other), [
                'lock_version' => $other->lock_version, 'simkug' => $this->simkugPayload(),
            ])->assertForbidden();
            $this->login();
        }
    }

    public function test_simkug_native_save_keeps_menu_tab_and_validation_inputs_and_resets_on_resubmission(): void
    {
        $p = $this->verified('impresfund');
        $url = route('pencairan.simkug.save', $p);
        $detailUrl = route('pencairan.show', ['pengajuan' => $p, 'menu' => 'transaksi', 'tab' => 'simkug']);
        $this->from($detailUrl)->patch($url, ['lock_version' => $p->lock_version, 'simkug' => $this->simkugPayload()])
            ->assertRedirect($detailUrl)->assertSessionHas('success', 'Data SIMKUG berhasil disimpan.');
        $this->from($detailUrl)->patch($url, [
            'lock_version' => $p->fresh()->lock_version,
            'simkug' => array_replace($this->simkugPayload(), ['dc' => '']),
        ])->assertRedirect($detailUrl)->assertSessionHasErrors('simkug.dc')->assertSessionHasInput('simkug.kode_akun', '000123');
        $this->action($p, 'returnToUser', ['remark' => 'Perbaiki'])->assertOk();
        $this->login();
        $this->action($p, 'submit')->assertOk();
        $this->assertNull($p->fresh()->simkug_data);
        $this->assertSame(1, $p->histories()->where('action', 'simkug')->count());
        $this->login('admin', '', 'admin');
        $this->action($p, 'verify')->assertOk();
        $this->get(route('pencairan.show', $p))->assertOk()->assertViewHas('simkugData', fn ($data) => $data['kode_akun'] === '001');
    }

    public static function nonTransactionStatuses(): array
    {
        return [['draft'], ['submitted'], ['returned'], ['completed'], ['cancelled']];
    }

    #[DataProvider('nonTransactionStatuses')]
    public function test_simkug_is_hidden_for_other_statuses(string $status): void
    {
        $p = $this->draft();
        $p->status = $status;
        $p->save();
        $this->get(route('pencairan.show', $p))->assertOk()
            ->assertSee('>Detail</button>', false)
            ->assertSee('>Riwayat</button>', false)
            ->assertDontSee('>SIMKUG</button>', false)
            ->assertDontSee('id="pengajuan-simkug-'.$p->id.'"', false);
    }

    public function test_detail_workflow_actions_share_toolbar_and_use_confirmation_modals(): void
    {
        $p = $this->submitted();
        $this->login('admin', '', 'admin');
        $response = $this->get(route('pencairan.show', $p))->assertOk()
            ->assertDontSee("confirm('Lanjutkan", false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[@data-workflow-actions]/a')->length);
        $this->assertSame(3, $xpath->query('//div[@data-workflow-actions]/button[@data-bs-toggle="modal"]')->length);
        $this->assertSame(0, $xpath->query('//section//form[@data-workflow-form]')->length);
        foreach (['verify', 'return', 'cancel'] as $action) {
            $modalId = 'workflow-'.$action.'-'.$p->id;
            $this->assertSame(1, $xpath->query('//div[@data-workflow-actions]/button[@data-bs-target="#'.$modalId.'"]')->length);
            $formPath = '//div[@id="'.$modalId.'"]//form';
            $this->assertSame(route('pencairan.'.$action, $p), $xpath->evaluate('string('.$formPath.'/@action)'));
            $this->assertSame(1, $xpath->query($formPath.'/input[@name="confirmed" and @value="1"]')->length);
            $this->assertSame(1, $xpath->query($formPath.'/input[@name="lock_version" and @value="'.$p->lock_version.'"]')->length);
            $this->assertSame(1, $xpath->query($formPath.'//button[@type="submit"]')->length);
            $this->assertSame($action === 'verify' ? 0 : 1, $xpath->query($formPath.'//textarea[@name="remark" and @required and @maxlength="5000"]')->length);
        }
        $this->post(route('pencairan.return', $p), ['lock_version' => $p->lock_version, 'confirmed' => '1', 'remark' => 'Mohon perbaiki rekening'])->assertRedirect();
        $this->assertSame('returned', $p->fresh()->status);
        $this->assertSame('Mohon perbaiki rekening', $p->histories()->where('action', 'return')->first()->remark);
    }

    public function test_user_draft_cancel_modal_requires_and_sends_reason(): void
    {
        $p = $this->draft();
        $this->get(route('pencairan.show', $p))->assertOk()
            ->assertSee('data-bs-target="#workflow-cancel-'.$p->id.'"', false)
            ->assertSee('Alasan batalkan (wajib)')
            ->assertSee('name="remark" rows="4" maxlength="5000" required', false)
            ->assertDontSee('data-bs-target="#workflow-verify-', false);
        $this->post(route('pencairan.cancel', $p), ['lock_version' => $p->lock_version, 'confirmed' => '1', 'remark' => 'Pengajuan tidak diperlukan'])->assertRedirect();
        $this->assertSame('cancelled', $p->fresh()->status);
        $this->assertSame('Pengajuan tidak diperlukan', $p->histories()->where('action', 'cancel')->first()->remark);
    }

    #[DataProvider('kinds')]
    public function test_admin_can_create_edit_submit_and_revise_across_pp(string $jenis): void
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
        $this->patchJson(route('pencairan.update', $p), ['lock_version'=>$p->fresh()->lock_version, 'details'=>[]])->assertStatus(409);
        $this->action($p,'submit')->assertStatus(409);
        $this->action($p,'verify')->assertOk();
    }

    public function test_admin_own_draft_cancel_and_forged_pp_are_protected(): void
    {
        $this->login('admin', 'PP01', 'admin-pp');
        $this->postJson(route('pencairan.store'), ['jenis'=>'beban','details'=>[],'kode_pp'=>'OTHER'])->assertUnprocessable();
        $p = $this->draft();
        $this->login('admin', 'PP02', 'other-admin');
        $this->action($p, 'cancel', ['remark'=>'Batal draft unit lain'])->assertOk();
        $this->assertSame('cancelled', $p->fresh()->status);
        $this->login('admin', '', 'admin');
        $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee('Tambah Pengajuan');
        $this->postJson(route('pencairan.store'), ['jenis'=>'beban','details'=>[]])->assertUnprocessable();
        $response = $this->postJson(route('pencairan.store'), [
            'jenis' => 'beban', 'details' => [], 'unit_kode_pp' => 'PP02',
        ])->assertCreated();
        $this->assertSame('PP02', PencairanPengajuan::where('uuid', $response->json('uuid'))->firstOrFail()->kode_pp);
    }

    #[DataProvider('kinds')]
    public function test_admin_submits_for_selected_sheet_unit_and_unit_cannot_change_after_save(string $jenis): void
    {
        $this->login('admin', 'PP02', 'admin-unit');
        $response = $this->postJson(route('pencairan.store'), [
            'jenis' => $jenis, 'unit_kode_pp' => ' pp01 ', 'nama_pp' => 'Forged Name',
            'drk_id' => str_repeat('a', 64), 'uraian_pengajuan' => 'Narasi', 'details' => [$this->row($jenis)],
        ])->assertCreated();
        $p = PencairanPengajuan::where('uuid', $response->json('uuid'))->firstOrFail();
        $this->assertSame('PP01', $p->kode_pp);
        $this->assertSame('PP Satu', $p->nama_pp);
        $this->assertSame('admin-unit', $p->histories()->first()->actor_name);
        $this->get(route('pencairan.edit', $p))->assertOk()->assertSee('PP Satu')
            ->assertDontSee('id="pilih-unit"', false);
        $this->patchJson(route('pencairan.update', $p), [
            'lock_version' => $p->lock_version, 'unit_kode_pp' => 'PP02', 'details' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_kode_pp');
        $this->patchJson(route('pencairan.update', $p), [
            'lock_version' => $p->lock_version, 'unit_kode_pp' => 'PP01',
            'drk_id' => str_repeat('a', 64), 'uraian_pengajuan' => 'Narasi diperbarui', 'details' => [$this->row($jenis)],
        ])->assertOk();
        $this->action($p, 'submit')->assertOk();
        $this->assertSame('submitted', $p->fresh()->status);
        $this->assertSame(['PP01', 4, 2026], end($this->lookups));
        $this->assertStringEndsWith('/8910', $p->fresh()->nomor_pengajuan);
        $this->action($p, 'returnToUser', ['remark' => 'Perbaiki'])->assertOk();
        $this->get(route('pencairan.show', $p))->assertOk()->assertSee('Edit / Submit');
        $this->get(route('pencairan.edit', $p))->assertOk();
        $this->action($p, 'submit')->assertOk();
    }

    public function test_selected_unit_is_validated_for_role_and_drk_ownership(): void
    {
        $payload = ['jenis' => 'beban', 'unit_kode_pp' => 'PP02', 'drk_id' => str_repeat('a', 64), 'details' => []];
        $this->postJson(route('pencairan.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('unit_kode_pp');
        $this->login('admin', 'PP01', 'admin-unit');
        $this->postJson(route('pencairan.store'), array_replace($payload, ['unit_kode_pp' => 'UNKNOWN']))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_kode_pp');
        $this->postJson(route('pencairan.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('drk_id');
        $this->assertSame(0, PencairanPengajuan::count());
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
            $p = PencairanPengajuan::where('uuid', $result->json('uuid'))->firstOrFail();
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Edit / Submit');
            $this->get(route('pencairan.edit',$p))->assertOk()->assertSee($kind === 'impresfund' ? 'Belanja' : '000123', false);
            $this->postJson(route('pencairan.submit',$p), ['lock_version'=>$p->lock_version, 'idempotency_key'=>'ui-'.$kind])->assertOk();
            $this->get(route('pencairan.pengajuan'))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()
                ->assertSee('Revisi 1')->assertSee('Diajukan untuk verifikasi')
                ->assertSee('Menunggu Verifikasi')->assertSee('Pengajuan dibuat');
            $this->login('admin','','admin');
            $this->get(route('pencairan.verifikasi'))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Batalkan')->assertSee('Verifikasi');
            $this->postJson(route('pencairan.verify',$p), ['lock_version'=>$p->fresh()->lock_version,'confirmed'=>true])->assertOk();
            $this->get(route('pencairan.transaksi',$kind))->assertOk()->assertSee($p->fresh()->nomor_pengajuan);
            $this->get(route('pencairan.show',$p))->assertOk()->assertSee('Tanggal Penyelesaian')
                ->assertDontSee('Lampiran opsional')->assertDontSee('name="attachments[]"', false);
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
            $this->assertSame($jenis === 'honor' ? '95.00' : '77.00', $p->total_neto);
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
        $this->assertSame('000001/2026/3110/8910', $p->nomor_pengajuan);
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
        $p = PencairanPengajuan::where('uuid', $response->json('uuid'))->firstOrFail();
        $this->assertNull($p->drk_source_id);
        $this->action($p, 'submit')->assertUnprocessable();
    }

    public function test_honor_master_code_is_saved_with_its_full_value(): void
    {
        $p = $this->draft('honor');

        $this->assertSame('PPH-21', $p->detailRows()[0]['kode_jenis']);
        $this->assertSame('5,00', $p->detailRows()[0]['pajak']);
        $this->assertSame('95,00', $p->detailRows()[0]['neto']);
        $this->assertDatabaseHas('pengajuan_honor_details', [
            'pengajuan_id' => $p->id,
            'kode_jenis' => 'PPH-21',
        ]);
        $this->assertSame('5.00', $p->fresh()->total_pajak);
    }

    public function test_honor_tax_is_recalculated_server_side_instead_of_trusting_pasted_value(): void
    {
        $row = $this->row('honor');
        $row['nominal'] = '1.000,01';
        $row['pajak'] = '999.999';
        $row['neto'] = '0';
        $p = $this->draft('honor', [$row]);

        $this->assertSame('50,00', $p->detailRows()[0]['pajak']);
        $this->assertSame('950,01', $p->detailRows()[0]['neto']);
        $this->assertSame('50.00', $p->total_pajak);
        $this->assertSame('950.01', $p->total_neto);
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
        $this->postJson('/_workflow', ['jenis' => 'honor', 'details' => [['kode_jenis' => 'BAD']]])->assertUnprocessable()->assertJsonValidationErrors('details.0.kode_jenis');
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => array_fill(0, 501, ['no' => '1'])])->assertUnprocessable();
    }

    public function test_submit_calculation_rejects_bank_values_outside_the_active_master(): void
    {
        try {
            app(PencairanService::class)->calculate('beban', [[
                'nominal' => '100',
                'pajak' => '0',
                'neto' => '100',
                'bank' => 'BANK TIDAK TERDAFTAR',
                'nama' => 'Nama',
                'rekening' => '001',
            ]], true);
            $this->fail('Expected bank validation to reject a non-master value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('details.0.bank', $exception->errors());
        }
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
        $this->assertSame('1000000/2026/3110/8910', $p->nomor_pengajuan);
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
        $p = PencairanPengajuan::where('uuid', $response->json('uuid'))->firstOrFail();
        $this->assertSame('100.00', $p->total_bruto);
        $this->assertSame(2026, $p->tahun_anggaran); $this->assertSame(4, $p->triwulan);
        $this->assertSame('PP Satu', $p->nama_pp); $this->assertSame('draft', $p->status);
        $this->assertSame('10000,00', $p->header_snapshot['saldo_terkini']);
    }

    public function test_drk_budget_options_sum_all_committed_form_types_for_the_matching_period(): void
    {
        foreach ([
            ['impresfund', 'submitted'],
            ['beban', 'verified'],
            ['honor', 'completed'],
            ['impresfund', 'draft'],
            ['beban', 'returned'],
            ['honor', 'cancelled'],
        ] as [$jenis, $status]) {
            $pengajuan = $this->draft($jenis);
            $pengajuan->update(['status' => $status]);
        }

        $otherYear = $this->draft('honor');
        $otherYear->update(['status' => 'completed', 'tahun_anggaran' => 2025]);

        $options = app(PencairanService::class)->drkOptionsForPeriod('PP01', 4, 2026);

        $this->assertSame('300,00', $options[0]['anggaran_terpakai']);
        $this->assertSame('9700,00', $options[0]['saldo_terkini']);
    }

    public function test_auth_roles_and_cross_pp_are_enforced_for_every_action(): void
    {
        $p = $this->draft();
        $this->login('user', 'PP02', 'user2');
        foreach (['submit', 'cancel', 'verify', 'returnToUser', 'complete'] as $action) { $this->action($p, $action)->assertForbidden(); }
        $this->patchJson('/_workflow/'.$p->uuid, ['lock_version' => 0, 'details' => []])->assertForbidden();
        $this->login('admin', '', 'admin');
        $this->postJson('/_workflow', ['jenis' => 'beban', 'details' => []])->assertUnprocessable();
        $this->action($p, 'submit')->assertOk();
        $this->patchJson('/_workflow/'.$p->uuid, ['lock_version' => 0, 'details' => []])->assertStatus(409);
        $this->withSession(['user_authenticated' => false]);
        $this->action($p, 'cancel')->assertForbidden();
    }

    public function test_saldo_equal_passes_short_negative_missing_invalid_and_offline_fail_closed(): void
    {
        $p = $this->draft();
        foreach (['99,99', '-1,00'] as $balance) {
            $this->options[0]['saldo_awal'] = $balance;
            $this->action($p, 'submit')->assertUnprocessable()->assertJsonValidationErrors('saldo');
        }
        foreach ([null, '', 'garbage'] as $balance) {
            $this->options[0]['saldo_awal'] = $balance;
            $this->action($p, 'submit')->assertStatus(503);
        }
        $this->offline = true;
        $this->action($p, 'submit')->assertStatus(503)->assertDontSee('secret credential');
        $this->assertSame('draft', $p->fresh()->status);
        $this->assertDatabaseCount('pengajuan_number_sequences', 0);
        $this->assertDatabaseCount('pengajuan_revisions', 0);
        $this->assertDatabaseCount('pengajuan_histories', 1);
        $this->offline = false; $this->options[0]['saldo_awal'] = '100,00';
        $this->action($p, 'submit')->assertOk();
        $this->login('admin', '', 'admin'); $this->offline = true;
        $this->action($p, 'verify')->assertStatus(503);
        $this->offline = false; $this->options[0]['saldo_awal'] = '99,00';
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
        $this->patchJson('/_workflow/'.$p->uuid, ['lock_version' => $p->lock_version, 'drk_id' => $p->drk_source_id, 'uraian_pengajuan' => 'Revisi', 'details' => [$row]])->assertOk();
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
        $this->patchJson('/_workflow/'.$p->uuid, ['jenis' => 'honor', 'details' => [], 'lock_version' => 0])->assertUnprocessable();
        $payload = ['details' => [$this->row('beban')], 'drk_id' => $p->drk_source_id, 'uraian_pengajuan' => 'Updated', 'lock_version' => 0];
        $this->patchJson('/_workflow/'.$p->uuid, $payload)->assertOk();
        $this->patchJson('/_workflow/'.$p->uuid, $payload)->assertConflict();
        $this->action($p, 'submit', ['lock_version' => 0])->assertConflict();
        $this->action($p, 'submit')->assertOk();
        $this->patchJson('/_workflow/'.$p->uuid, array_replace($payload, ['lock_version' => 2]))->assertConflict();
        $this->login('admin', '', 'admin');
        $this->action($p, 'verify', ['lock_version' => 0])->assertConflict();
        $this->action($p, 'verify')->assertOk(); $this->login();
        $this->patchJson('/_workflow/'.$p->uuid, array_replace($payload, ['lock_version' => 3]))->assertConflict();
    }

    public function test_submit_idempotency_retries_identical_payload_before_stale_version_checks(): void
    {
        $p = $this->draft(); $payload = ['lock_version' => 0, 'idempotency_key' => 'same'];
        $first = $this->postJson('/_workflow/'.$p->uuid.'/submit', $payload)->assertOk()->json();
        $this->offline = true;
        $this->postJson('/_workflow/'.$p->uuid.'/submit', $payload)->assertOk()->assertExactJson($first);
        $this->postJson('/_workflow/'.$p->uuid.'/submit', $payload + ['confirmed' => true])->assertConflict();
        $this->assertDatabaseCount('pengajuan_revisions', 1); $this->assertDatabaseCount('pengajuan_histories', 2);
        $this->assertDatabaseCount('pengajuan_idempotencies', 1);
    }

    public function test_complete_idempotency_includes_file_content_and_does_not_duplicate_storage(): void
    {
        $p = $this->verified();
        $make = fn ($content) => UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n".$content);
        $payload = ['lock_version' => $p->lock_version, 'confirmed' => true, 'idempotency_key' => 'same', 'completion_date' => '2026-10-31'];
        $first = $this->postJson('/_workflow/'.$p->uuid.'/complete', $payload + ['attachments' => [$make('a')]])->assertOk()->json();
        $this->postJson('/_workflow/'.$p->uuid.'/complete', $payload + ['attachments' => [$make('a')]])->assertOk()->assertExactJson($first);
        $this->postJson('/_workflow/'.$p->uuid.'/complete', $payload + ['attachments' => [$make('b')]])->assertConflict();
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
        $response = $this->post('/_workflow', ['jenis' => 'beban', 'details' => []])->assertRedirect()->assertSessionHas('success');
        $this->assertMatchesRegularExpression('#^https?://[^/]+/pencairan/pengajuan/[0-9a-f-]{36}$#', $response->headers->get('Location'));
        $this->login('user', 'PP01', 'user1', '99'); $p = $this->draft();
        $this->assertDatabaseCount('pengajuan_actors', 1);
        $this->post('/_workflow/'.$p->uuid.'/cancel', ['confirmed' => '1', 'lock_version' => 0])->assertRedirect()->assertSessionHas('success');
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