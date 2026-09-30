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

$email = '';
$error = '';

$success = '';
if (!empty($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf
        )
    ) {
        $error = 'Invalid request. Please try again.';
    } elseif ($email === '') {
        $error = 'Please enter your email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email,
                password,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = 'Unable to process login.';

        } else {

            $stmt->bind_param('s', $email);

            if ($stmt->execute()) {

                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {

                    $customer = $result->fetch_assoc();

                    if (
                        $customer['status'] !== 'active'
                    ) {

                        $error =
                            'Your customer account is inactive.';

                    } elseif (
                        !password_verify(
                            $password,
                            $customer['password']
                        )
                    ) {

                        $error =
                            'Invalid email or password.';

                    } else {

                        session_regenerate_id(true);

                        $_SESSION['customer_id'] =
                            (int) $customer['id'];

                        $_SESSION['customer_name'] =
                            $customer['name'];

                        $_SESSION['customer_email'] =
                            $customer['email'];

                        $_SESSION['csrf_token'] =
                            bin2hex(random_bytes(32));

                        header('Location: index.php');
                        exit;
                    }

                } else {

                    $error =
                        'Invalid email or password.';
                }

            } else {

                $error =
                    'Unable to process login.';
            }

            $stmt->close();
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

    <title>Customer Login | Smart Tiffin</title>

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
                Homemade happiness, delivered.
            </p>

        </div>


        <?php if ($success !== ''): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo e($success); ?>
            </div>

        <?php endif; ?>


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


            <div class="mb-4">

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
                        placeholder="Enter your password"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-customer-primary w-100"
            >
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Login to Smart Tiffin
            </button>

        </form>


        <div class="text-center mt-4">

            <small class="text-muted">
                Don't have an account?
                <a href="register.php">Create one</a>
            </small>

        </div>

    </div>

</div>

</body>
</html>