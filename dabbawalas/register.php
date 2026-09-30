<?php

/*
|--------------------------------------------------------------------------
| Dabbawala Sign Up / Registration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| If already logged in
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['dabbawala_id']) &&
    is_numeric($_SESSION['dabbawala_id']) &&
    (int) $_SESSION['dabbawala_id'] > 0
) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

$name = '';
$email = '';
$phone = '';

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
| Registration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['csrf_token'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            $error = 'Please enter your full name.';

        } elseif (strlen($name) < 3) {

            $error = 'Name must contain at least 3 characters.';

        } elseif ($email === '') {

            $error = 'Please enter your email address.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } elseif ($phone === '') {

            $error = 'Please enter your phone number.';

        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {

            $error = 'Please enter a valid 10-digit phone number.';

        } elseif ($password === '') {

            $error = 'Please enter a password.';

        } elseif (strlen($password) < 6) {

            $error = 'Password must contain at least 6 characters.';

        } elseif ($confirm_password === '') {

            $error = 'Please confirm your password.';

        } elseif ($password !== $confirm_password) {

            $error = 'Passwords do not match.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check Existing Email
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM dabbawalas
                WHERE email = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error = 'Unable to process registration right now.';

            } else {

                $stmt->bind_param('s', $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows > 0) {

                    $error = 'An account with this email already exists.';

                }

                $stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Existing Phone
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $stmt = $conn->prepare("
                SELECT id
                FROM dabbawalas
                WHERE phone = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error = 'Unable to process registration right now.';

            } else {

                $stmt->bind_param('s', $phone);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows > 0) {

                    $error = 'An account with this phone number already exists.';

                }

                $stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Account
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
             * New dabbawalas are inactive initially.
             *
             * Admin can activate them from:
             * Admin → Dabbawala Management
             */

            $status = 'inactive';
            $availability_status = 'available';

            $stmt = $conn->prepare("
                INSERT INTO dabbawalas
                (
                    name,
                    email,
                    phone,
                    password,
                    availability_status,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {

                $error = 'Unable to create your account right now.';

            } else {

                $stmt->bind_param(
                    'ssssss',
                    $name,
                    $email,
                    $phone,
                    $hashed_password,
                    $availability_status,
                    $status
                );

                if ($stmt->execute()) {

                    /*
                    |--------------------------------------------------------------------------
                    | Registration Successful
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    $success =
                        'Registration successful! Your account is waiting for admin approval.';

                    /*
                     * Clear form fields
                     */
                    $name = '';
                    $email = '';
                    $phone = '';

                } else {

                    $error =
                        'Unable to create your account. Please try again.';
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

    <title>Dabbawala Sign Up | Smart Tiffin</title>

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

        .signup-wrapper {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;
        }

        .signup-card {

            width: 100%;

            max-width: 480px;

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

            background:
                linear-gradient(
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

        .input-group-text {

            border-radius: 12px 0 0 12px;

            border-color: #e5e7eb;
        }

        .btn-signup {

            min-height: 52px;

            border: 0;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--secondary)
                );

            color: #ffffff;

            font-weight: 700;

            transition: 0.2s ease;
        }

        .btn-signup:hover {

            color: #ffffff;

            transform: translateY(-1px);

            box-shadow:
                0 10px 22px rgba(255, 107, 0, 0.22);
        }

        .login-link {

            color: var(--primary);

            font-weight: 700;

            text-decoration: none;
        }

        .login-link:hover {

            text-decoration: underline;
        }

        .signup-footer {

            margin-top: 25px;

            text-align: center;

            color: #9ca3af;

            font-size: 13px;
        }

    </style>

</head>

<body>

<div class="signup-wrapper">

    <div class="signup-card">

        <!-- Brand -->

        <div class="text-center">

            <div class="brand-icon">

                <i class="bi bi-bicycle"></i>

            </div>

            <h2 class="brand-title">
                Dabbawala Sign Up
            </h2>

            <p class="brand-subtitle mb-4">
                Create your delivery partner account
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
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            </div>

        <?php endif; ?>


        <!-- Success -->

        <?php if ($success !== ''): ?>

            <div
                class="alert alert-success"
                role="alert"
            >

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                <div class="mt-2">

                    <a
                        href="login.php"
                        class="btn btn-sm btn-success"
                    >
                        Go to Login
                    </a>

                </div>

            </div>

        <?php endif; ?>


        <!-- Registration Form -->

        <?php if ($success === ''): ?>

        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <!-- Name -->

            <div class="mb-3">

                <label
                    for="name"
                    class="form-label"
                >
                    Full Name
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">

                        <i class="bi bi-person"></i>

                    </span>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Enter your full name"
                        autocomplete="name"
                        required
                    >

                </div>

            </div>


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
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Enter your email"
                        autocomplete="email"
                        required
                    >

                </div>

            </div>


            <!-- Phone -->

            <div class="mb-3">

                <label
                    for="phone"
                    class="form-label"
                >
                    Phone Number
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">

                        <i class="bi bi-telephone"></i>

                    </span>

                    <input
                        type="tel"
                        class="form-control"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Enter 10-digit phone number"
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="tel"
                        required
                    >

                </div>

            </div>


            <!-- Password -->

            <div class="mb-3">

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
                        placeholder="Create a password"
                        autocomplete="new-password"
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

                <small class="text-muted">
                    Minimum 6 characters
                </small>

            </div>


            <!-- Confirm Password -->

            <div class="mb-4">

                <label
                    for="confirm_password"
                    class="form-label"
                >
                    Confirm Password
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">

                        <i class="bi bi-shield-lock"></i>

                    </span>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="toggleConfirmPassword"
                        aria-label="Show password"
                    >

                        <i class="bi bi-eye"></i>

                    </button>

                </div>

            </div>


            <!-- Signup -->

            <button
                type="submit"
                class="btn btn-signup w-100"
            >

                <i class="bi bi-person-plus-fill me-2"></i>

                Create Dabbawala Account

            </button>

        </form>

        <?php endif; ?>


        <!-- Login -->

        <div class="text-center mt-4">

            <span class="text-muted">
                Already have an account?
            </span>

            <a
                href="login.php"
                class="login-link ms-1"
            >
                Login
            </a>

        </div>


        <!-- Footer -->

        <div class="signup-footer">

            Smart Tiffin Delivery & Tracking System

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Password Toggle
|--------------------------------------------------------------------------
*/

const passwordInput =
    document.getElementById('password');

const togglePassword =
    document.getElementById('togglePassword');

if (togglePassword) {

    togglePassword.addEventListener(
        'click',
        function () {

            const icon =
                this.querySelector('i');

            if (passwordInput.type === 'password') {

                passwordInput.type = 'text';

                icon.classList.remove('bi-eye');

                icon.classList.add('bi-eye-slash');

            } else {

                passwordInput.type = 'password';

                icon.classList.remove('bi-eye-slash');

                icon.classList.add('bi-eye');
            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Confirm Password Toggle
|--------------------------------------------------------------------------
*/

const confirmPasswordInput =
    document.getElementById('confirm_password');

const toggleConfirmPassword =
    document.getElementById('toggleConfirmPassword');

if (toggleConfirmPassword) {

    toggleConfirmPassword.addEventListener(
        'click',
        function () {

            const icon =
                this.querySelector('i');

            if (
                confirmPasswordInput.type === 'password'
            ) {

                confirmPasswordInput.type = 'text';

                icon.classList.remove('bi-eye');

                icon.classList.add('bi-eye-slash');

            } else {

                confirmPasswordInput.type = 'password';

                icon.classList.remove('bi-eye-slash');

                icon.classList.add('bi-eye');

            }

        }
    );

}

</script>

</body>

</html>