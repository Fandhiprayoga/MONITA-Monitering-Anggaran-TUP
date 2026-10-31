<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\GoogleSheetService;
use Mockery\MockInterface;

class PencairanFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'user_data' => ['kode_pp' => 'TEST', 'nama_pp' => 'Unit Test'],
        ]);
        $this->travelTo(now()->setDate(2026, 10, 29));
        $this->mock(GoogleSheetService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getUnitsFromSheet')->andReturn(['TEST' => 'Unit Test']);
            $mock->shouldReceive('getPencairanDrkOptions')->with('TEST', 4, 2026)->andReturn([
                [
                    'id' => '2', 'tipe' => 'Operasional', 'drk_tup' => 'DRK-TEST', 'kode_drk' => 'SIMKUG-TEST',
                    'kode_pp' => '00123', 'nama_pp' => 'Nama PP dari Sheet',
                    'kode_akun' => '001', 'nama_akun' => 'Akun Test', 'uraian_drk' => 'Kegiatan Test',
                    'saldo_awal' => '1.000.000,00', 'anggaran_terpakai' => '100.000,00',
                    'saldo_terkini' => '900.000,00',
                ],
            ])->byDefault();
        });
    }

    public function test_forms_share_header_and_render_their_own_details(): void
    {
        foreach (['impresfund' => 'Subtotal', 'beban' => 'Nominal Setelah Pajak', 'honor' => 'Total Diterima'] as $kind => $column) {
            $this->withSession(['user_authenticated' => true])
                ->get(route('pencairan.form', $kind))
                ->assertOk()
                ->assertSee('Nama Akun')
                ->assertSee('Kode PP')
                ->assertSee('Uraian Pengajuan')
                ->assertSee('Nominal Diajukan')
                ->assertSee('Pilih DRK')
                ->assertSee('id="cari-drk"', false)
                ->assertSee('id="hasil-cari-drk"', false)
                ->assertSee('DRK-TEST')
                ->assertSee('pencairan-header.js')
                ->assertDontSee('Total Pengajuan:')
                ->assertDontSee('data-total>', false)
                ->assertDontSee('data-total-tax', false)
                ->assertDontSee('data-total-net', false)
                ->assertDontSee('Total Pajak:')
                ->assertDontSee('Total Transfer (Neto):')
                ->assertSee($column)
                ->assertSee('Salin Semua ke Excel')
                ->assertSee('pencairan-grid.css')
                ->assertSee('role="grid"', false)
                ->assertSee('aria-multiselectable="true"', false)
                ->assertSee('data-grid-address', false)
                ->assertSee('data-grid-select-all', false)
                ->assertSee('data-grid-select-column="0"', false)
                ->assertSee('Pilih seluruh tabel')
                ->assertSee('Nomor baris terpisah dari input No.')
                ->assertDontSee('spreadsheet-column', false)
                ->assertSee('aria-label="Pilih kolom No"', false)
                ->assertSee('Shift+panah')
                ->assertSee('klik dua kali atau F2')
                ->assertSee('Simpan sebagai Draft')
                ->assertSee('Submit Pengajuan')
                ->assertDontSee('Simpan Pengajuan (belum tersedia)')
                ->assertSee('data-save-draft', false)
                ->assertSee('type="submit" class="btn btn-primary">', false)
                ->assertSee('pencairan-submit.js')
                ->assertSee('data-jenis="'.$kind.'"', false)
                ->assertSee('Kode Akun')
                ->assertSee('id="kode-akun" name="kode_akun" class="form-control bg-light" type="text" readonly', false)
                ->assertSee('Nomor resmi diberikan saat submit pertama.');
        }
    }

    public function test_honor_renders_all_detail_columns_and_grid(): void
    {
        $this->withSession(['user_authenticated' => true])
            ->get(route('pencairan.form', 'honor'))
            ->assertOk()
            ->assertSee('data-grid-body', false)
            ->assertSee('pencairan-grid.js')
            ->assertSee('WNI / WNA')
            ->assertSee('Nominal Honor')
            ->assertSee('Potongan Pajak')
            ->assertSee('Total Diterima')
            ->assertSee('Nama Penerima')
            ->assertSee('Nama Rekening')
            ->assertSee('Nama Bank')
            ->assertSee('Nomor Rekening')
            ->assertSee('No KTP / Paspor')
            ->assertSee('No NPWP')
            ->assertSee('Total nominal sebelum pajak (bruto).');
    }

    public function test_impresfund_explains_manual_subtotal_and_clipboard_order(): void
    {
        $this->withSession(['user_authenticated' => true])
            ->get(route('pencairan.form', 'impresfund'))
            ->assertOk()
            ->assertSee('No, Uraian, Volume, Tarif, Subtotal')
            ->assertSee('kosongkan untuk kembali otomatis')
            ->assertSee('yang disalin lalu ditempel kembali menjadi nilai manual.');
    }

    public function test_all_forms_place_grid_and_specific_instructions_in_help_modal(): void
    {
        foreach ([
            'impresfund' => 'Subtotal otomatis = Volume x Tarif',
            'beban' => 'Nominal Setelah Pajak diisi manual',
            'honor' => 'Isi kewarganegaraan dengan WNI atau WNA',
        ] as $kind => $instruction) {
            $response = $this->withSession(['user_authenticated' => true])
                ->get(route('pencairan.form', $kind))
                ->assertOk()
                ->assertSee('data-bs-target="#grid-help-modal"', false)
                ->assertSee('aria-labelledby="grid-help-title"', false)
                ->assertSee('modal-dialog-scrollable')
                ->assertSee('aria-describedby="grid-help-summary"', false);

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//*[@id="grid-help-modal"]')->length);
            $this->assertSame(0, $xpath->query('//form//*[@id="grid-help-modal" or @id="grid-help"]')->length);
            $help = $xpath->query('//*[@id="grid-help"]')->item(0)->textContent;
            $this->assertStringContainsString($instruction, $help);
            $this->assertStringContainsString('Shift+panah', $help);
            $this->assertStringContainsString('Salin Semua ke Excel', $help);
        }
    }

    public function test_forms_require_login_and_reject_unknown_types(): void
    {
        $this->get(route('pencairan.form', 'beban'))->assertRedirect(route('login'));
        $this->withSession(['user_authenticated' => true])
            ->get('/pencairan/form/unknown')->assertNotFound();
    }

    public function test_drk_uses_session_unit_and_current_quarter_not_query_parameters(): void
    {
        $this->withSession(['user_authenticated' => true])
            ->get(route('pencairan.form', 'beban').'?kode_pp=OTHER&tw=1')
            ->assertOk()
            ->assertViewHas('activeTw', 4)
            ->assertViewHas('drkOptions', function (array $options) {
                return $options[0]['kode_pp'] === '00123'
                    && $options[0]['nama_pp'] === 'Nama PP dari Sheet'
                    && $options[0]['kode_drk'] === 'SIMKUG-TEST'
                    && $options[0]['saldo_terkini'] === '900.000,00';
            })
            ->assertSee('id="nama-akun" name="nama_akun" class="form-control bg-light" type="text" readonly', false);
    }

    public function test_empty_drk_and_source_errors_are_explicit(): void
    {
        $service = app(GoogleSheetService::class);
        $service->shouldReceive('getPencairanDrkOptions')->with('TEST', 4, 2026)->once()->andReturn([]);
        $this->withSession(['user_authenticated' => true])
            ->get(route('pencairan.form', 'honor'))
            ->assertOk()
            ->assertSee('Tidak ada DRK untuk unit Anda')
            ->assertViewHas('drkError', null);

        $service->shouldReceive('getPencairanDrkOptions')->with('TEST', 4, 2026)->once()
            ->andThrow(new \RuntimeException('Source unavailable'));
        $this->get(route('pencairan.form', 'impresfund'))
            ->assertOk()
            ->assertSee('Gagal memuat DRK dan saldo anggaran.')
            ->assertViewHas('drkOptions', [])
            ->assertDontSee('Source unavailable');
    }

    public function test_missing_session_unit_is_rejected(): void
    {
        $this->withSession(['user_authenticated' => true, 'user_data' => []])
            ->get(route('pencairan.form', 'beban'))
            ->assertForbidden();
    }
}
