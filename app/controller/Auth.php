<?php

declare(strict_types=1);

namespace app\controller;

use app\exception\AuthenticationException;
use app\service\AuthService;
use app\service\CsrfTokenService;
use think\facade\Session;
use think\Request;
use think\Response;

final class Auth
{
    public function login(CsrfTokenService $csrf): Response
    {
        if (is_array(Session::get('partner_auth'))) {
            return redirect('/dashboard');
        }

        return view('auth/login', [
            'csrf_token' => $csrf->issue('login'),
            'error' => '',
            'username' => '',
        ]);
    }

    public function authenticate(Request $request, AuthService $auth, CsrfTokenService $csrf): Response
    {
        $username = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        $token = (string) $request->post('_token', '');

        if (!$csrf->validate('login', $token)) {
            return view('auth/login', [
                'csrf_token' => $csrf->issue('login'),
                'error' => '页面已过期，请重新提交',
                'username' => $username,
            ])->code(419);
        }

        try {
            $auth->attempt(
                $username,
                $password,
                (string) $request->ip(),
                (string) $request->header('user-agent', '')
            );

            return redirect('/dashboard');
        } catch (AuthenticationException $exception) {
            return view('auth/login', [
                'csrf_token' => $csrf->issue('login'),
                'error' => $exception->getMessage(),
                'username' => $username,
            ])->code(422);
        }
    }

    public function logout(Request $request, AuthService $auth, CsrfTokenService $csrf): Response
    {
        if (!$csrf->validate('logout', (string) $request->post('_token', ''))) {
            return response('页面已过期，请重新提交', 419);
        }

        $auth->logout((string) $request->ip(), (string) $request->header('user-agent', ''));

        return redirect('/login');
    }
}
