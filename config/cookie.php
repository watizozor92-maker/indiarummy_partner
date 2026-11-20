<?php
// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    //  cookie 启用安全传输
    'secure'    => filter_var(getenv('COOKIE_SECURE') ?: false, FILTER_VALIDATE_BOOL),
    // httponly设置
    'httponly'  => true,
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite 设置，支持 'strict' 'lax'
    'samesite'  => 'lax',
];
