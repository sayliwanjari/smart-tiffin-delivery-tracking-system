<?php

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,   // set true only if you're on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

}