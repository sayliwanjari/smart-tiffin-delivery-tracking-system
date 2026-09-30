<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (
    isset($_SESSION['customer_id']) &&
    (int) $_SESSION['customer_id'] > 0
) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$name = '';
$email = '';
$phone = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf
        )
    ) {
        $error = 'Invalid request. Please try again.';
    } elseif ($name === '') {
        $error = 'Please enter your name.';
    } elseif ($email === '') {
        $error = 'Please enter your email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($phone === '') {
        $error = 'Please enter your phone number.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } elseif ($password === '') {
        $error = 'Please enter a password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {

        // Check whether the email is already registered
        $checkStmt = $conn->prepare("
            SELECT id FROM users WHERE email = ? LIMIT 1
        ");

        if (!$checkStmt) {

            $error = 'Unable to process registration.';

        } else {

            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {

                $error = 'An account with this email already exists.';
                $checkStmt->close();

            } else {

                $checkStmt->close();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $conn->prepare("
                    INSERT INTO users (name, email, phone, password, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");

                if (!$insertStmt) {

                    $error = 'Unable to process registration.';

                } else {

                    $insertStmt->bind_param(
                        'ssss',
                        $name,
                        $email,
                        $phone,
                        $hashedPassword
                    );

                    if ($insertStmt->execute()) {

                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['registration_success'] =
                            'Account created successfully. Please login to continue.';

                        header('Location: login.php');
                        exit;

                    } else {

                        $error = 'Unable to create your account. Please try again.';
                    }

                    $insertStmt->close();
                }
            }
        }
    }
}

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Account | Smart Tiffin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="/mumbaiDabbawala/admin/assets/css/customer.css"
    >

</head>

<body class="customer-auth-page">

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="text-center mb-4">

            <div class="brand-icon">
                <i class="bi bi-egg-fried"></i>
            </div>

            <h2 class="fw-bold mt-3 mb-1">
                Smart Tiffin
            </h2>

            <p class="text-muted mb-0">
                Create your account to get started.
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo e($error); ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION['csrf_token']); ?>"
            >

            <div class="mb-3">

                <label
                    class="form-label fw-semibold"
                    for="name"
                >
                    Full Name
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        value="<?php echo e($name); ?>"
                        placeholder="Your full name"
                        required
                    >

                </div>

            </div>


            <div class="mb-3">

                <label
                    class="form-label fw-semibold"
                    for="email"
                >
                    Email Address
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-envelope"></i>
                    </span>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?php echo e($email); ?>"
                        placeholder="you@example.com"
                        required
                    >

                </div>

            </div>


            <div class="mb-3">

                <label
                    class="form-label fw-semibold"
                    for="phone"
                >
                    Phone Number
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-telephone"></i>
                    </span>

                    <input
                        type="tel"
                        class="form-control"
                        id="phone"
                        name="phone"
                        value="<?php echo e($phone); ?>"
                        placeholder="10-digit phone number"
                        maxlength="10"
                        required
                    >

                </div>

            </div>


            <div class="mb-3">

                <label
                    class="form-label fw-semibold"
                    for="password"
                >
                    Password
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="At least 8 characters"
                        required
                    >

                </div>

            </div>


            <div class="mb-4">

                <label
                    class="form-label fw-semibold"
                    for="confirm_password"
                >
                    Confirm Password
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter your password"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-customer-primary w-100"
            >
                <i class="bi bi-person-plus me-2"></i>
                Create Account
            </button>

        </form>


        <div class="text-center mt-4">

            <small class="text-muted">
                Already have an account?
                <a href="login.php">Login here</a>
            </small>

        </div>

    </div>

</div>

</body>
</html>