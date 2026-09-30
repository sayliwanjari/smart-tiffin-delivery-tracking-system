<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Already logged in?
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Form variables
|--------------------------------------------------------------------------
*/

$email = '';
$error_message = '';


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $submitted_token = $_POST['csrf_token'] ?? '';

    /*
     * CSRF validation
     */
    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $submitted_token
        )
    ) {
        $error_message = 'Invalid request. Please try again.';
    }

    /*
     * Validation
     */
    elseif ($email === '') {
        $error_message = 'Please enter your email address.';
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    }

    elseif ($password === '') {
        $error_message = 'Please enter your password.';
    }

    /*
     * Authenticate admin
     */
    else {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email,
                password,
                status
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error_message = 'Unable to process login. Please try again.';
        } else {

            $stmt->bind_param('s', $email);

            if (!$stmt->execute()) {

                $error_message =
                    'Unable to process login. Please try again.';

            } else {

                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {

                    $admin = $result->fetch_assoc();

                    /*
                     * Check account status
                     */
                    if (
                        isset($admin['status']) &&
                        strtolower($admin['status']) !== 'active'
                    ) {

                        $error_message =
                            'Your admin account is inactive.';

                    }

                    /*
                     * Verify password
                     */
                    elseif (
                        !password_verify(
                            $password,
                            $admin['password']
                        )
                    ) {

                        $error_message =
                            'Invalid email or password.';

                    }

                    /*
                     * Successful login
                     */
                    else {

                        /*
                         * Prevent session fixation.
                         */
                        session_regenerate_id(true);

                        /*
                         * Store the admin identity expected
                         * by the existing admin pages.
                         */
                        $_SESSION['admin_id'] = (int) $admin['id'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];

                        /*
                         * New CSRF token after authentication.
                         */
                        $_SESSION['csrf_token'] =
                            bin2hex(random_bytes(32));

                        /*
                         * Redirect to dashboard.
                         */
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {

                    $error_message =
                        'Invalid email or password.';
                }
            }

            $stmt->close();
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

    <title>Admin Login | Smart Tiffin</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- Existing admin CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

</head>

<body>

<div class="min-vh-100 d-flex align-items-center justify-content-center bg-light p-3">

    <div
        class="card border-0 shadow-sm"
        style="width:100%; max-width:430px;"
    >

        <div class="card-body p-4 p-md-5">

            <!-- Branding -->

            <div class="text-center mb-4">

                <div
                    class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                    style="
                        width:64px;
                        height:64px;
                        background:#ff6b00;
                        color:white;
                        font-size:28px;
                    "
                >
                    <i class="bi bi-box-seam"></i>
                </div>

                <h3 class="fw-bold mb-1">
                    Smart Tiffin
                </h3>

                <p class="text-muted mb-0">
                    Admin Panel
                </p>

            </div>


            <!-- Error -->

            <?php if ($error_message !== ''): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?php
                    echo htmlspecialchars(
                        $error_message,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

            <?php endif; ?>


            <!-- Login Form -->

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>"
                >


                <!-- Email -->

                <div class="mb-3">

                    <label
                        for="email"
                        class="form-label fw-semibold"
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
                            value="<?php
                            echo htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            placeholder="admin@example.com"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>


                <!-- Password -->

                <div class="mb-4">

                    <label
                        for="password"
                        class="form-label fw-semibold"
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
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>


                <!-- Submit -->

                <button
                    type="submit"
                    class="btn btn-primary w-100 py-2"
                >

                    <i class="bi bi-box-arrow-in-right me-2"></i>

                    Login

                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>