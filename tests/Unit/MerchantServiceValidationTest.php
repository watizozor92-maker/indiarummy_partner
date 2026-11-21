<?php

declare(strict_types=1);

namespace tests\Unit;

use app\service\MerchantService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MerchantServiceValidationTest extends TestCase
{
    public function testLimitAllowsUnlimitedSentinelAndNonNegativeValues(): void
    {
        $service = (new ReflectionClass(MerchantService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(MerchantService::class, 'limitInteger');

        self::assertSame(-1, $method->invoke($service, '-1', '信用额度'));
        self::assertSame(0, $method->invoke($service, '0', '信用额度'));
        self::assertSame(1000, $method->invoke($service, '1000', '信用额度'));
    }

    public function testLimitRejectsValuesBelowUnlimitedSentinel(): void
    {
        $service = (new ReflectionClass(MerchantService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(MerchantService::class, 'limitInteger');

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($service, '-2', '信用额度');
    }

    public function testMerchantPasswordUsesTheRequiredStrengthRules(): void
    {
        $service = (new ReflectionClass(MerchantService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(MerchantService::class, 'isStrongPassword');

        self::assertTrue($method->invoke($service, 'Merchant#2026'));
        self::assertFalse($method->invoke($service, 'merchant2026'));
        self::assertFalse($method->invoke($service, 'Short#1A'));
    }
}
