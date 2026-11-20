<?php
declare(strict_types=1);
namespace Tests\Security;
use app\service\InternalApiSigner;use PHPUnit\Framework\TestCase;
final class InternalApiSignerTest extends TestCase
{
    public function testSignatureIsStableRegardlessOfParameterOrder():void{$signer=new InternalApiSigner();$secret=str_repeat('s',32);$a=$signer->sign(1700000000,'0123456789abcdef',['gameid'=>419,'act'=>'reloadChannel','channelid'=>10000],'',$secret);$b=$signer->sign(1700000000,'0123456789abcdef',['channelid'=>10000,'gameid'=>419,'act'=>'reloadChannel'],'',$secret);self::assertSame($a,$b);self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/',$a);}
}
