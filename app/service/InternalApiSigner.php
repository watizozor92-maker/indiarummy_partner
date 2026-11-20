<?php
declare(strict_types=1);
namespace app\service;
final class InternalApiSigner
{
    public function sign(int $timestamp,string $nonce,array $parameters,string $body,string $secret):string
    {ksort($parameters,SORT_STRING);$lines=[];foreach($parameters as $key=>$value)$lines[]=$key.'='.$value;$canonical=$timestamp."\n".$nonce."\n".implode("\n",$lines)."\n".sha1($body);return hash_hmac('sha1',$canonical,$secret);}
}
