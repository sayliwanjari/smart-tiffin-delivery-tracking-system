<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Add Customer';
$active_page = 'customers';


function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$name = '';
$email = '';
$phone = '';
$password = '';
$confirm_password = '';
$status = 'active';

$address_label = 'Home';
$address_line = '';
$landmark = '';
$city = '';
$state = '';
$postal_code = '';

$error_message = '';


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF validation
     */
    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error_message = 'Invalid request. Please try again.';

    } else {

        /*
         * Read form values
         */
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $status = $_POST['status'] ?? 'active';

        $address_label = trim(
            $_POST['address_label'] ?? ''
        );

        $address_line = trim(
            $_POST['address_line'] ?? ''
        );

        $landmark = trim(
            $_POST['landmark'] ?? ''
        );

        $city = trim(
            $_POST['city'] ?? ''
        );

        $state = trim(
            $_POST['state'] ?? ''
        );

        $postal_code = trim(
            $_POST['postal_code'] ?? ''
        );


        /*
         * Validation
         */

        if ($name === '') {

            $error_message =
                'Customer name is required.';

        } elseif (strlen($name) > 100) {

            $error_message =
                'Customer name cannot exceed 100 characters.';

        } elseif (
            !preg_match(
                "/^[a-zA-Z .'-]+$/",
                $name
            )
        ) {

            $error_message =
                'Customer name contains invalid characters.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error_message =
                'Please enter a valid email address.';

        } elseif (strlen($email) > 150) {

            $error_message =
                'Email address is too long.';

        } elseif (
            !preg_match(
                '/^[0-9]{10}$/',
                $phone
            )
        ) {

            $error_message =
                'Phone number must contain exactly 10 digits.';

        } elseif ($password === '') {

            $error_message =
                'Password is required.';

        } elseif (strlen($password) < 8) {

            $error_message =
                'Password must be at least 8 characters.';

        } elseif ($password !== $confirm_password) {

            $error_message =
                'Passwords do not match.';

        } elseif (
            !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {

            $error_message =
                'Invalid customer status.';

        } elseif ($address_label === '') {

            $error_message =
                'Address label is required.';

        } elseif (strlen($address_label) > 50) {

            $error_message =
                'Address label cannot exceed 50 characters.';

        } elseif ($address_line === '') {

            $error_message =
                'Delivery address is required.';

        } elseif (strlen($address_line) > 255) {

            $error_message =
                'Delivery address cannot exceed 255 characters.';

        } elseif (strlen($landmark) > 150) {

            $error_message =
                'Landmark cannot exceed 150 characters.';

        } elseif ($city === '') {

            $error_message =
                'City is required.';

        } elseif (strlen($city) > 100) {

            $error_message =
                'City cannot exceed 100 characters.';

        } elseif ($state === '') {

            $error_message =
                'State is required.';

        } elseif (strlen($state) > 100) {

            $error_message =
                'State cannot exceed 100 characters.';

        } elseif (
            !preg_match(
                '/^[0-9]{6}$/',
                $postal_code
            )
        ) {

            $error_message =
                'Postal code must contain exactly 6 digits.';

        }


        /*
         * Check duplicate email.
         */

        if ($error_message === '') {

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error_message =
                    'Unable to validate customer email.';

            } else {

                $stmt->bind_param(
                    's',
                    $email
                );

                if (!$stmt->execute()) {

                    $error_message =
                        'Unable to validate customer email.';

                } else {

                    $result = $stmt->get_result();

                    if (
                        $result &&
                        $result->num_rows > 0
                    ) {

                        $error_message =
                            'A customer with this email already exists.';
                    }
                }

                $stmt->close();
            }
        }


        /*
         * Check duplicate phone.
         *
         * Phone is not UNIQUE in the current schema,
         * so this is enforced at application level.
         */

        if ($error_message === '') {

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE phone = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $error_message =
                    'Unable to validate customer phone number.';

            } else {

                $stmt->bind_param(
                    's',
                    $phone
                );

                if (!$stmt->execute()) {

                    $error_message =
                        'Unable to validate customer phone number.';

                } else {

                    $result = $stmt->get_result();

                    if (
                        $result &&
                        $result->num_rows > 0
                    ) {

                        $error_message =
                            'A customer with this phone number already exists.';
                    }
                }

                $stmt->close();
            }
        }


        /*
         * Create customer + address together.
         *
         * Transaction guarantees that we don't end up with
         * a customer without the delivery address required
         * by the order system.
         */

        if ($error_message === '') {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($hashed_password === false) {

                $error_message =
                    'Unable to secure the customer password.';

            } else {

                $conn->begin_transaction();

                try {

                    /*
                     * Insert customer.
                     */

                    $stmt = $conn->prepare("
                        INSERT INTO users
                        (
                            name,
                            email,
                            phone,
                            password,
                            status
                        )
                        VALUES
                        (?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new Exception(
                            'Unable to prepare customer creation.'
                        );
                    }

                    $stmt->bind_param(
                        'sssss',
                        $name,
                        $email,
                        $phone,
                        $hashed_password,
                        $status
                    );

                    if (!$stmt->execute()) {

                        if ($stmt->errno === 1062) {

                            throw new Exception(
                                'A customer with this email already exists.'
                            );

                        }

                        throw new Exception(
                            'Unable to create customer.'
                        );
                    }

                    $customer_id = $conn->insert_id;

                    $stmt->close();


                    /*
                     * Insert delivery address.
                     */

                    $stmt = $conn->prepare("
                        INSERT INTO addresses
                        (
                            user_id,
                            address_label,
                            address_line,
                            landmark,
                            city,
                            state,
                            postal_code,
                            is_default
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1)
                    ");

                    if (!$stmt) {

                        throw new Exception(
                            'Unable to prepare delivery address.'
                        );
                    }

                    $stmt->bind_param(
                        'issssss',
                        $customer_id,
                        $address_label,
                        $address_line,
                        $landmark,
                        $city,
                        $state,
                        $postal_code
                    );

                    if (!$stmt->execute()) {

                        throw new Exception(
                            'Unable to save the delivery address.'
                        );
                    }

                    $stmt->close();


                    /*
                     * Everything succeeded.
                     */

                    $conn->commit();

                    $_SESSION['csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    $_SESSION['customer_success'] =
                        'Customer and delivery address added successfully.';

                    header(
                        'Location: index.php'
                    );

                    exit;

                } catch (Throwable $exception) {

                    $conn->rollback();

                    $error_message =
                        $exception->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h2 class="fw-bold mb-1">
                Add Customer
            </h2>

            <p class="text-muted mb-0">
                Create a customer account and delivery address.
            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left me-2"></i>
                Back to Customers
            </a>

        </div>

    </div>

</div>


<?php if ($error_message !== ''): ?>

    <div
        class="alert alert-danger d-flex align-items-center"
        role="alert"
    >

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        <div>
            <?php echo e($error_message); ?>
        </div>

    </div>

<?php endif; ?>


<div class="admin-data-card">

    <div class="admin-data-card-header">

        <h5 class="mb-1">
            Customer Information
        </h5>

        <small class="text-muted">
            Customer account details.
        </small>

    </div>

    <div class="card-body p-4">

        <form method="POST" action="" novalidate>

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrf_token); ?>"
            >

            <div class="row g-4">

                <!-- NAME -->

                <div class="col-12 col-md-6">

                    <label
                        for="name"
                        class="form-label fw-semibold"
                    >
                        Full Name <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        maxlength="100"
                        value="<?php echo e($name); ?>"
                        placeholder="Enter customer name"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="col-12 col-md-6">

                    <label
                        for="email"
                        class="form-label fw-semibold"
                    >
                        Email Address <span class="text-danger">*</span>
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?php echo e($email); ?>"
                        placeholder="customer@example.com"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="col-12 col-md-6">

                    <label
                        for="phone"
                        class="form-label fw-semibold"
                    >
                        Phone Number <span class="text-danger">*</span>
                    </label>

                    <input
                        type="tel"
                        class="form-control"
                        id="phone"
                        name="phone"
                        maxlength="10"
                        minlength="10"
                        value="<?php echo e($phone); ?>"
                        placeholder="10-digit phone number"
                        inputmode="numeric"
                        required
                    >

                </div>


                <!-- STATUS -->

                <div class="col-12 col-md-6">

                    <label
                        for="status"
                        class="form-label fw-semibold"
                    >
                        Status
                    </label>

                    <select
                        class="form-select"
                        id="status"
                        name="status"
                    >

                        <option
                            value="active"
                            <?php
                            echo $status === 'active'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?php
                            echo $status === 'inactive'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- PASSWORD -->

                <div class="col-12 col-md-6">

                    <label
                        for="password"
                        class="form-label fw-semibold"
                    >
                        Password <span class="text-danger">*</span>
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="255"
                        placeholder="Minimum 8 characters"
                        required
                    >

                    <small class="text-muted">
                        Minimum 8 characters.
                    </small>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="col-12 col-md-6">

                    <label
                        for="confirm_password"
                        class="form-label fw-semibold"
                    >
                        Confirm Password <span class="text-danger">*</span>
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        maxlength="255"
                        placeholder="Re-enter password"
                        required
                    >

                </div>


                <!-- ADDRESS SECTION -->

                <div class="col-12">

                    <hr>

                    <h5 class="fw-bold mb-1">
                        Delivery Address
                    </h5>

                    <p class="text-muted small mb-0">
                        This address will be available when creating an order.
                    </p>

                </div>


                <!-- ADDRESS LABEL -->

                <div class="col-12 col-md-6">

                    <label
                        for="address_label"
                        class="form-label fw-semibold"
                    >
                        Address Label <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="address_label"
                        name="address_label"
                        maxlength="50"
                        value="<?php echo e($address_label); ?>"
                        placeholder="Home"
                        required
                    >

                </div>


                <!-- CITY -->

                <div class="col-12 col-md-6">

                    <label
                        for="city"
                        class="form-label fw-semibold"
                    >
                        City <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="city"
                        name="city"
                        maxlength="100"
                        value="<?php echo e($city); ?>"
                        placeholder="Nagpur"
                        required
                    >

                </div>


                <!-- ADDRESS -->

                <div class="col-12">

                    <label
                        for="address_line"
                        class="form-label fw-semibold"
                    >
                        Delivery Address <span class="text-danger">*</span>
                    </label>

                    <textarea
                        class="form-control"
                        id="address_line"
                        name="address_line"
                        rows="3"
                        maxlength="255"
                        placeholder="House/Flat number, street, area"
                        required
                    ><?php echo e($address_line); ?></textarea>

                </div>


                <!-- LANDMARK -->

                <div class="col-12 col-md-6">

                    <label
                        for="landmark"
                        class="form-label fw-semibold"
                    >
                        Landmark
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="landmark"
                        name="landmark"
                        maxlength="150"
                        value="<?php echo e($landmark); ?>"
                        placeholder="Near..."
                    >

                </div>


                <!-- STATE -->

                <div class="col-12 col-md-6">

                    <label
                        for="state"
                        class="form-label fw-semibold"
                    >
                        State <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="state"
                        name="state"
                        maxlength="100"
                        value="<?php echo e($state); ?>"
                        placeholder="Maharashtra"
                        required
                    >

                </div>


                <!-- POSTAL CODE -->

                <div class="col-12 col-md-6">

                    <label
                        for="postal_code"
                        class="form-label fw-semibold"
                    >
                        Postal Code <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="postal_code"
                        name="postal_code"
                        maxlength="6"
                        minlength="6"
                        value="<?php echo e($postal_code); ?>"
                        placeholder="440001"
                        inputmode="numeric"
                        required
                    >

                </div>


                <!-- SUBMIT -->

                <div class="col-12">

                    <hr>

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">

                        <a
                            href="index.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-person-plus me-2"></i>
                            Create Customer
                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>