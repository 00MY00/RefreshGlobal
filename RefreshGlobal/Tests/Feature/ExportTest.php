<?php

namespace Modules\RefreshGlobal\Tests\Feature;

use Modules\RefreshGlobal\Tests\TestCase;

class ExportTest extends TestCase
{
    protected function csv($user, array $query = [])
    {
        $response = $this->actingAs($user)->get(route('refreshglobal.export', $query));
        $response->assertStatus(200);
        ob_start();
        $response->baseResponse->sendContent();

        return ob_get_clean();
    }

    public function testExportHasBomHeaderAndOnlyAllowedRows()
    {
        $s = $this->s;
        $csv = $this->csv($s['bob'], ['mb' => [$s['billing']->id, $s['support']->id, $s['sales']->id]]);
        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
        $this->assertStringContainsString($s['prefix'].'-SUPPORT-OPEN', $csv);
        $this->assertStringContainsString($s['prefix'].'-SALES-OPEN', $csv);
        $this->assertStringNotContainsString($s['prefix'].'-BILLING-', $csv);
        $this->assertStringNotContainsString($s['prefix'].'-ARCHIVED', $csv);
        $this->assertStringNotContainsString($s['prefix'].'-SALES-DRAFT', $csv);
    }

    public function testExportFollowsFilters()
    {
        $s = $this->s;
        $csv = $this->csv($s['bob'], ['mb' => [$s['sales']->id]]);
        $this->assertStringContainsString($s['prefix'].'-SALES-OPEN', $csv);
        $this->assertStringNotContainsString($s['prefix'].'-SUPPORT-OPEN', $csv);
    }

    public function testExportIsCapped()
    {
        $s = $this->s;
        config(['refreshglobal.export_max_rows' => 2]);
        $csv = $this->csv($s['admin'], ['q' => $s['prefix']]);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(3, $lines); // header + 2 rows
    }

    public function testFormulaInjectionIsNeutralised()
    {
        $s = $this->s;
        $csv = $this->csv($s['bob'], ['q' => $s['prefix'].'-FORMULA']);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|;)"?=HYPERLINK/m', $csv);
    }

    public function testOnlyAssignedUserExport()
    {
        $s = $this->s;
        $csv = $this->csv($s['carol']);
        $this->assertStringContainsString($s['prefix'].'-BILLING-CAROL', $csv);
        $this->assertStringNotContainsString($s['prefix'].'-BILLING-OTHER', $csv);
    }
}
