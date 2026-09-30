<?php

/*
|--------------------------------------------------------------------------
| Dabbawala Login
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

/*
 * Make sure session is active.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * If already logged in, go directly to dashboard.
 */
if (
    isset($_SESSION['dabbawala_id']) &&
    is_numeric($_SESSION['dabbawala_id']) &&
    (int) $_SESSION['dabbawala_id'] > 0
) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF validation
     */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        /*
         * Get submitted values.
         */
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        /*
         * Validate email.
         */
        if ($email === '') {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password === '') {
            $error = 'Please enter your password.';
        } else {

            /*
             * Find dabbawala by email.
             *
             * Existing columns:
             * id
             * name
             * email
             * phone
             * password
             * profile_image
             * availability_status
             * status
             * created_at
             * updated_at
             */
            $sql = "
                SELECT
                    id,
                    name,
                    email,
                    password,
                    status
                FROM dabbawalas
                WHERE email = ?
                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $error = 'Unable to process login right now.';
            } else {

                $stmt->bind_param('s', $email);

                if (!$stmt->execute()) {

                    $error = 'Unable to process login right now.';

                } else {

                    $result = $stmt->get_result();
                    $dabbawala = $result->fetch_assoc();

                    if (!$dabbawala) {

                        $error = 'Invalid email or password.';

                    } else {

                        /*
                         * Check account status.
                         *
                         * Only active dabbawalas can login.
                         */
                        if (
                            !isset($dabbawala['status']) ||
                            strtolower(trim((string) $dabbawala['status'])) !== 'active'
                        ) {

                            $error = 'Your dabbawala account is inactive. Please contact the administrator.';

                        } elseif (
                            !password_verify(
                                $password,
                                (string) $dabbawala['password']
                            )
                        ) {

                            $error = 'Invalid email or password.';

                        } else {

                            /*
                             * Successful authentication.
                             *
                             * Regenerate session ID for security.
                             */
                            session_regenerate_id(true);

                            $_SESSION['dabbawala_id'] = (int) $dabbawala['id'];
                            $_SESSION['dabbawala_name'] = (string) $dabbawala['name'];
                            $_SESSION['dabbawala_email'] = (string) $dabbawala['email'];

                            /*
                             * Generate a new CSRF token after login.
                             */
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            /*
                             * Redirect to dabbawala dashboard.
                             */
                            header('Location: index.php');
                            exit;
                        }
                    }
                }

                $stmt->close();
            }
        }
    }
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

    <title>Dabbawala Login | Smart Tiffin</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: #ff6b00;
            --secondary: #f59e0b;
            --dark: #111827;
            --light: #f8fafc;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(
                    circle at top right,
                    rgba(255, 107, 0, 0.12),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #fff7ed,
                    #f8fafc
                );
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color: var(--dark);
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: #ffffff;
            border: 0;
            border-radius: 24px;
            padding: 35px;
            box-shadow:
                0 20px 60px rgba(17, 24, 39, 0.12);
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--secondary)
            );
            color: white;
            font-size: 32px;
            box-shadow:
                0 10px 25px rgba(255, 107, 0, 0.25);
        }

        .brand-title {
            font-weight: 800;
            margin-bottom: 5px;
        }

        .brand-subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            min-height: 50px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding-left: 15px;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow:
                0 0 0 0.2rem rgba(255, 107, 0, 0.12);
        }

        .btn-login {
            min-height: 52px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--secondary)
            );
            color: #ffffff;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .btn-login:hover {
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow:
                0 10px 22px rgba(255, 107, 0, 0.22);
        }

        .login-footer {
            margin-top: 25px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }
    </style>

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <!-- Brand -->
        <div class="text-center">

            <div class="brand-icon">
                <i class="bi bi-bicycle"></i>
            </div>

            <h2 class="brand-title">
                Dabbawala Login
            </h2>

            <p class="brand-subtitle mb-4">
                Sign in to manage your deliveries
            </p>

        </div>

        <!-- Error -->
        <?php if ($error !== ''): ?>

            <div
                class="alert alert-danger d-flex align-items-center"
                role="alert"
            >
                <i class="bi bi-exclamation-circle-fill me-2"></i>

                <div>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

        <?php endif; ?>

        <!-- Login Form -->
        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
            >

            <!-- Email -->
            <div class="mb-3">

                <label
                    for="email"
                    class="form-label"
                >
                    Email Address
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">
                        <i class="bi bi-envelope"></i>
                    </span>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Enter your email"
                        autocomplete="email"
                        required
                    >

                </div>

            </div>

            <!-- Password -->
            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">
                        <i class="bi bi-lock"></i>
                    </span>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="togglePassword"
                        aria-label="Show password"
                    >
                        <i class="bi bi-eye"></i>
                    </button>

                </div>

            </div>

            <!-- Login Button -->
            <button
                type="submit"
                class="btn btn-login w-100"
            >
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Login to Dashboard
            </button>

        </form>

        <div class="login-footer">
            Smart Tiffin Delivery & Tracking System
        </div>

    </div>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {

        const icon = this.querySelector('i');

        if (passwordInput.type === 'password') {

            passwordInput.type = 'text';

            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');

        } else {

            passwordInput.type = 'password';

            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
</script>

</body>
</html>