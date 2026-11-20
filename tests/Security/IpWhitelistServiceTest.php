<?php

declare(strict_types=1);

namespace Tests\Security;

use app\service\IpWhitelistService;
use PHPUnit\Framework\TestCase;

final class IpWhitelistServiceTest extends TestCase
{
    private IpWhitelistService $service;

    protected function setUp(): void
    {
        $this->service = new IpWhitelistService();
    }

    public function testExactAndCidrRules(): void
    {
        self::assertTrue($this->service->contains('127.0.0.1', '127.0.0.1'));
        self::assertFalse($this->service->contains('127.0.0.1', '127.0.0.2'));
        self::assertTrue($this->service->contains('10.20.0.0/16', '10.20.8.9'));
        self::assertFalse($this->service->contains('10.20.0.0/16', '10.21.8.9'));
        self::assertTrue($this->service->contains('2001:db8::/32', '2001:db8::1234'));
        self::assertFalse($this->service->contains('2001:db8::/32', '2001:db9::1234'));
    }

    public function testRuleValidationRejectsInvalidPrefixes(): void
    {
        self::assertTrue($this->service->isValidRule('192.168.1.0/24'));
        self::assertTrue($this->service->isValidRule('2001:db8::/48'));
        self::assertFalse($this->service->isValidRule('192.168.1.0/33'));
        self::assertFalse($this->service->isValidRule('not-an-ip'));
    }
}
