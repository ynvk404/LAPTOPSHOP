<?php
// config_session.php

declare(strict_types=1);

$SESSION_NAME     = 'laptopshop_sess';
$SESSION_LIFETIME = 60 * 60 * 2; // 2 giờ
$LIMIT_ATTEMPTS   = 6;           // số lần thử tối đa
$LOCKOUT_SECONDS  = 60 * 5;      // khóa trong 5 phút
$secretKey        = 'super_strong_secret_key'; // đổi trong config riêng

$secureFlag = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_name($SESSION_NAME);
session_set_cookie_params([
    'lifetime' => $SESSION_LIFETIME,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secureFlag,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>