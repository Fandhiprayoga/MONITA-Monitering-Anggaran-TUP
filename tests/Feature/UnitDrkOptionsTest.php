<?php

namespace Tests\Feature;

use App\Services\GoogleSheetService;
use Google\Service\Sheets;
use Google\Service\Sheets\Resource\SpreadsheetsValues;
use Google\Service\Sheets\ValueRange;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UnitDrkOptionsTest extends TestCase
{
    public function test_drk_options_filter_unit_and_preserve_account_rows_and_sheet_balances(): void
    {
        $values = [
            ['OTHER', 'Operasional', 'PRIVATE-DRK', '001', 'Private Account', 'Private Activity', 999, 0, 999],
            [' test ', 'Operasional', 'DRK-1', '001', 'Akun Satu', 'Kegiatan Satu', 1000000.5, 200000.25, 800000.25, ' 00123 ', 'Nama PP Sheet', 'M ignored', 'SIMKUG-001'],
            ['TEST', 'Remun', 'DRK-1', '002', 'Akun Dua', 'Kegiatan Dua', 500000, 600000, -100000],
            ['TEST', 'Operasional', '', '003', 'No DRK'],
        ];
        $resource = Mockery::mock(SpreadsheetsValues::class);
        $resource->shouldReceive('get')->once()->with(
            'test-sheet',
            'RAW Data TW IV!B2:N3000',
            ['valueRenderOption' => 'UNFORMATTED_VALUE']
        )->andReturn(new ValueRange(['values' => $values]));
        $sheets = new Sheets();
        $sheets->spreadsheets_values = $resource;
        $reflection = new ReflectionClass(GoogleSheetService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('service')->setValue($service, $sheets);
        $reflection->getProperty('spreadsheetId')->setValue($service, 'test-sheet');

        $options = $service->getUnitDrkOptions('TEST', 4);

        $this->assertCount(2, $options);
        $this->assertSame(['3', '4'], array_column($options, 'id'));
        $this->assertSame(['DRK-1', 'DRK-1'], array_column($options, 'drk_tup'));
        $this->assertSame(['SIMKUG-001', ''], array_column($options, 'kode_drk'));
        $this->assertSame(['00123', ''], array_column($options, 'kode_pp'));
        $this->assertSame(['Nama PP Sheet', ''], array_column($options, 'nama_pp'));
        $this->assertSame('Akun Satu', $options[0]['nama_akun']);
        $this->assertSame(['001', '002'], array_column($options, 'kode_akun'));
        $this->assertSame('Kegiatan Satu', $options[0]['uraian_drk']);
        $this->assertSame('1.000.000,50', $options[0]['saldo_awal']);
        $this->assertSame('200.000,25', $options[0]['anggaran_terpakai']);
        $this->assertSame('800.000,25', $options[0]['saldo_terkini']);
        $this->assertSame('-100.000,00', $options[1]['saldo_terkini']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($options));
    }
}
