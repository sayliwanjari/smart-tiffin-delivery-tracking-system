<?php

require_once __DIR__ . '/../../config/session.php';

if (
    !isset($_SESSION['customer_id']) ||
    (int) $_SESSION['customer_id'] < 1
) {
    header('Location: login.php');
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'] ?? 'Customer';
$customer_email = $_SESSION['customer_email'] ?? '';