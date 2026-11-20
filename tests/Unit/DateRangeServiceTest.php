<?php
declare(strict_types=1);
namespace Tests\Unit;
use app\service\DateRangeService;use InvalidArgumentException;use PHPUnit\Framework\TestCase;
final class DateRangeServiceTest extends TestCase
{
    public function testParsesInclusiveDateRange():void{$range=(new DateRangeService())->parse('2026-11-01','2026-11-07');self::assertSame('2026-11-01 00:00:00',$range['fromSql']);self::assertSame('2026-11-07 23:59:59',$range['toSql']);}
    public function testRejectsInvalidAndOversizedRanges():void{$service=new DateRangeService();$this->expectException(InvalidArgumentException::class);$service->parse('2025-01-01','2026-11-07',7,30);}
}
