<?php

require_once __DIR__ . '/../config/session.php';

unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['customer_email']
);

header('Location: login.php');
exit;