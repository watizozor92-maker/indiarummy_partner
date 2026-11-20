<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Session;

final class CsrfTokenService
{
    public function issue(string $purpose): string
    {
        $key = $this->key($purpose);
        $token = (string) Session::get($key, '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set($key, $token);
        }

        return $token;
    }

    public function validate(string $purpose, string $provided): bool
    {
        $key = $this->key($purpose);
        $expected = (string) Session::get($key, '');

        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    private function key(string $purpose): string
    {
        return '_csrf.' . preg_replace('/[^a-z0-9_-]/i', '', $purpose);
    }
}
