<?php

declare(strict_types=1);

namespace tests\Security;

use app\service\CsvExportService;
use PHPUnit\Framework\TestCase;

final class CsvExportServiceTest extends TestCase
{
    public function testItAddsBomAndNeutralizesSpreadsheetFormulas(): void
    {
        $content = (new CsvExportService())->build(['name', 'value'], [
            ['normal', '=HYPERLINK("https://example.test")'],
            ['plus', '  +1+1'],
        ]);
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString("'=HYPERLINK", $content);
        self::assertStringContainsString("'  +1+1", $content);
    }
}
