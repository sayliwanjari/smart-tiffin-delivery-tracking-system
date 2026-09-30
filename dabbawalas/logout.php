<?php

/*
|--------------------------------------------------------------------------
| Dabbawala Logout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Remove only dabbawala-specific session variables.
 */
unset(
    $_SESSION['dabbawala_id'],
    $_SESSION['dabbawala_name'],
    $_SESSION['dabbawala_email']
);

/*
 * Redirect to login.
 */
header('Location: login.php');
exit;