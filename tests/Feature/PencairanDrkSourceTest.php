<?php

namespace Tests\Feature;

use App\Services\GoogleSheetService;
use Google\Service\Sheets;
use Google\Service\Sheets\Resource\SpreadsheetsValues;
use Google\Service\Sheets\ValueRange;
use Mockery;
use Tests\TestCase;

class PencairanDrkSourceTest extends TestCase
{
    private function service(array $responses): GoogleSheetService
    {
        config(['pencairan.source_year' => 2026, 'pencairan.source_spreadsheet_id' => 'current', 'pencairan.archive_spreadsheet_ids' => [2025 => 'archive']]);
        $resource = Mockery::mock(SpreadsheetsValues::class);
        foreach ($responses as [$id, $rows]) {
            $resource->shouldReceive('get')->once()->with($id, 'RAW Data TW IV!B2:N3000', ['valueRenderOption' => 'UNFORMATTED_VALUE'])->andReturn(new ValueRange(['values' => $rows]));
        }
        $sheets = new Sheets(); $sheets->spreadsheets_values = $resource;
        $reflection = new \ReflectionClass(GoogleSheetService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('service')->setValue($service, $sheets);
        $reflection->getProperty('spreadsheetId')->setValue($service, 'original');
        return $service;
    }

    private function row(string $account = '001'): array
    {
        return [' pp01 ', 'Operasional', 'DRK-1', $account, 'Akun', 'Uraian', 1000.5, 100.25, 900.25, 'OTHER', 'Nama', '', 'SIM1'];
    }

    public function test_stable_id_ignores_row_order_text_label_and_simkug_changes_but_distinguishes_account_and_type(): void
    {
        $a = $this->row(); $b = $this->row('002');
        $changed = $a; $changed[4] = 'Renamed'; $changed[12] = 'Changed';
        $service = $this->service([['current', [$a, $b]], ['current', [$b, $changed]]]);
        $before = $service->getPencairanDrkOptions('PP01', 4, 2026);
        $after = $service->getPencairanDrkOptions('pp01', 4, 2026);
        $this->assertSame($before[0]['id'], $after[1]['id']);
        $this->assertNotSame($before[0]['id'], $before[1]['id']);
        $this->assertSame('PP01', $before[0]['kode_pp']);
        $this->assertSame('OTHER', $before[0]['kode_pp_sheet']);
        $this->assertSame('900,25', $before[0]['saldo_terkini']);
        $this->assertSame(GoogleSheetService::pencairanDrkId('PP01', $before[0]), $before[0]['id']);
    }

    public function test_missing_and_malformed_source_balances_are_never_replaced_by_zero(): void
    {
        $missing = array_slice($this->row(), 0, 6);
        $bad = $this->row('002'); $bad[8] = 'not a balance';
        $negative = $this->row('003'); $negative[8] = -100;
        $service = $this->service([['current', [$missing, $bad, $negative]]]);
        $options = $service->getPencairanDrkOptions('PP01', 4, 2026);
        $this->assertNull($options[0]['saldo_awal']); $this->assertNull($options[0]['saldo_terkini']);
        $this->assertNull($options[1]['saldo_terkini']);
        $this->assertSame('-100,00', $options[2]['saldo_terkini']);
    }

    public function test_historical_year_requires_explicit_archive_and_source_id_is_restored(): void
    {
        $service = $this->service([['archive', [$this->row()]], ['current', [$this->row()]]]);
        $this->assertCount(1, $service->getPencairanDrkOptions('PP01', 4, 2025));
        $this->assertCount(1, $service->getPencairanDrkOptions('PP01', 4, 2026));
        $this->assertSame('original', (new \ReflectionClass($service))->getProperty('spreadsheetId')->getValue($service));
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->expectException(\RuntimeException::class);
        $service->getPencairanDrkOptions('PP01', 4, 2027);
    }
}