<?php

require_once __DIR__ . '/../config/session.php';


/*
|--------------------------------------------------------------------------
| Remove admin authentication data
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email']
);


/*
|--------------------------------------------------------------------------
| Destroy session
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
 * Delete session cookie if cookies are being used.
 */
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();


/*
|--------------------------------------------------------------------------
| Redirect to login
|--------------------------------------------------------------------------
*/

header('Location: login.php');
exit;