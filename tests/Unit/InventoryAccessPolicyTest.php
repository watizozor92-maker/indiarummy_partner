<?php

declare(strict_types=1);

namespace tests\Unit;

use app\service\InventoryAccessPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InventoryAccessPolicyTest extends TestCase
{
    public function testNormalizesScopedDirectGrant(): void
    {
        $grant = (new InventoryAccessPolicy())->normalizeGrant([
            'visibility' => 'full', 'canViewLedger' => '1', 'adjustMode' => 'direct',
            'gameIds' => '419, 635,419', 'currencyCodes' => 'cny, usd',
            'singleLimit' => '1000.5000', 'dailyLimit' => '5000',
        ]);

        self::assertSame([419, 635], $grant['gameIds']);
        self::assertSame(['CNY', 'USD'], $grant['currencyCodes']);
        self::assertTrue($grant['canViewLedger']);
        self::assertTrue((new InventoryAccessPolicy())->scopeAllows($grant, 419, 'cny'));
        self::assertFalse((new InventoryAccessPolicy())->scopeAllows($grant, 698, 'CNY'));
    }

    public function testHiddenGrantCannotRetainAdjustmentCapability(): void
    {
        $grant = (new InventoryAccessPolicy())->normalizeGrant([
            'visibility' => 'none', 'canViewLedger' => 1, 'adjustMode' => 'request',
            'singleLimit' => '0', 'dailyLimit' => '0',
        ]);

        self::assertFalse($grant['canViewLedger']);
        self::assertSame('none', $grant['adjustMode']);
    }

    public function testDirectLimitsAreEnforced(): void
    {
        $policy = new InventoryAccessPolicy();
        self::assertTrue($policy->canDirect(100, 400, 200, 500));
        self::assertFalse($policy->canDirect(201, 0, 200, 500));
        self::assertFalse($policy->canDirect(101, 400, 200, 500));
    }

    public function testDirectModeRequiresPositiveLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InventoryAccessPolicy())->normalizeGrant([
            'visibility' => 'summary', 'adjustMode' => 'direct',
            'singleLimit' => '0', 'dailyLimit' => '0',
        ]);
    }
}
