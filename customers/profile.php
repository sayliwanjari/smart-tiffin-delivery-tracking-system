<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/customer_auth.php';

$page_title = 'My Profile';
$active_page = 'profile';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customer_id = (int) ($_SESSION['customer_id'] ?? 0);

if ($customer_id <= 0) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success_message = '';

/*
|--------------------------------------------------------------------------
| Edit state
|--------------------------------------------------------------------------
*/

$edit_address = null;

if (isset($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    if ($edit_id > 0) {

        $stmt = $conn->prepare("
            SELECT
                id,
                address_label,
                address_line,
                landmark,
                city,
                state,
                postal_code,
                is_default
            FROM addresses
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                'ii',
                $edit_id,
                $customer_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $edit_address = $result->fetch_assoc();

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Add / Update Address
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $csrf)) {

        $errors[] = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';

        $address_id = (int) ($_POST['address_id'] ?? 0);

        $address_label = trim($_POST['address_label'] ?? '');
        $address_line = trim($_POST['address_line'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        $is_default = isset($_POST['is_default']) ? 1 : 0;

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($address_label === '') {
            $errors[] = 'Please enter an address label.';
        }

        if ($address_line === '') {
            $errors[] = 'Please enter your complete address.';
        }

        if ($city === '') {
            $errors[] = 'Please enter your city.';
        }

        if ($state === '') {
            $errors[] = 'Please enter your state.';
        }

        if ($postal_code === '') {

            $errors[] = 'Please enter your postal code.';

        } elseif (!preg_match('/^[0-9]{6}$/', $postal_code)) {

            $errors[] = 'Please enter a valid 6-digit postal code.';
        }

        /*
        |--------------------------------------------------------------------------
        | Save Address
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            /*
            |--------------------------------------------------------------------------
            | Update Existing Address
            |--------------------------------------------------------------------------
            */

            if ($action === 'update' && $address_id > 0) {

                /*
                | Verify ownership first
                */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM addresses
                    WHERE id = ?
                      AND user_id = ?
                    LIMIT 1
                ");

                $owned = false;

                if ($stmt) {

                    $stmt->bind_param(
                        'ii',
                        $address_id,
                        $customer_id
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();

                    $owned = $result->num_rows > 0;

                    $stmt->close();
                }

                if (!$owned) {

                    $errors[] = 'Invalid address selected.';

                } else {

                    $conn->begin_transaction();

                    try {

                        /*
                        | If this address becomes default,
                        | remove default from other addresses.
                        */

                        if ($is_default) {

                            $stmt = $conn->prepare("
                                UPDATE addresses
                                SET is_default = 0
                                WHERE user_id = ?
                            ");

                            if (!$stmt) {
                                throw new Exception();
                            }

                            $stmt->bind_param(
                                'i',
                                $customer_id
                            );

                            if (!$stmt->execute()) {
                                throw new Exception();
                            }

                            $stmt->close();
                        }

                        $stmt = $conn->prepare("
                            UPDATE addresses
                            SET
                                address_label = ?,
                                address_line = ?,
                                landmark = ?,
                                city = ?,
                                state = ?,
                                postal_code = ?,
                                is_default = ?
                            WHERE id = ?
                              AND user_id = ?
                        ");

                        if (!$stmt) {
                            throw new Exception();
                        }

                        $stmt->bind_param(
                            'ssssssiii',
                            $address_label,
                            $address_line,
                            $landmark,
                            $city,
                            $state,
                            $postal_code,
                            $is_default,
                            $address_id,
                            $customer_id
                        );

                        if (!$stmt->execute()) {
                            throw new Exception();
                        }

                        $stmt->close();

                        $conn->commit();

                        $success_message = 'Address updated successfully.';

                        $edit_address = null;

                    } catch (Throwable $e) {

                        $conn->rollback();

                        $errors[] = 'Unable to update the address. Please try again.';
                    }
                }

            /*
            |--------------------------------------------------------------------------
            | Add New Address
            |--------------------------------------------------------------------------
            */

            } elseif ($action === 'add') {

                $conn->begin_transaction();

                try {

                    /*
                    | Check whether this is customer's first address.
                    */

                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total
                        FROM addresses
                        WHERE user_id = ?
                    ");

                    if (!$stmt) {
                        throw new Exception();
                    }

                    $stmt->bind_param(
                        'i',
                        $customer_id
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();

                    $row = $result->fetch_assoc();

                    $stmt->close();

                    $address_count = (int) ($row['total'] ?? 0);

                    /*
                    | First address automatically becomes default.
                    */

                    if ($address_count === 0) {
                        $is_default = 1;
                    }

                    /*
                    | Remove default from other addresses.
                    */

                    if ($is_default) {

                        $stmt = $conn->prepare("
                            UPDATE addresses
                            SET is_default = 0
                            WHERE user_id = ?
                        ");

                        if (!$stmt) {
                            throw new Exception();
                        }

                        $stmt->bind_param(
                            'i',
                            $customer_id
                        );

                        if (!$stmt->execute()) {
                            throw new Exception();
                        }

                        $stmt->close();
                    }

                    /*
                    | Insert address
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
                        (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new Exception();
                    }

                    $stmt->bind_param(
                        'issssssi',
                        $customer_id,
                        $address_label,
                        $address_line,
                        $landmark,
                        $city,
                        $state,
                        $postal_code,
                        $is_default
                    );

                    if (!$stmt->execute()) {
                        throw new Exception();
                    }

                    $stmt->close();

                    $conn->commit();

                    $success_message = 'Address added successfully.';

                } catch (Throwable $e) {

                    $conn->rollback();

                    $errors[] = 'Unable to save the address. Please try again.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Delete Address
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {

        $address_id = (int) ($_POST['address_id'] ?? 0);

        if ($address_id <= 0) {

            $errors[] = 'Invalid address selected.';

        } else {

            /*
            | Get address before deleting
            */

            $stmt = $conn->prepare("
                SELECT is_default
                FROM addresses
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");

            $address_to_delete = null;

            if ($stmt) {

                $stmt->bind_param(
                    'ii',
                    $address_id,
                    $customer_id
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $address_to_delete = $result->fetch_assoc();

                $stmt->close();
            }

            if (!$address_to_delete) {

                $errors[] = 'Address not found.';

            } else {

                $was_default = (int) $address_to_delete['is_default'];

                $conn->begin_transaction();

                try {

                    $stmt = $conn->prepare("
                        DELETE FROM addresses
                        WHERE id = ?
                          AND user_id = ?
                    ");

                    if (!$stmt) {
                        throw new Exception();
                    }

                    $stmt->bind_param(
                        'ii',
                        $address_id,
                        $customer_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception();
                    }

                    $stmt->close();

                    /*
                    | If default address was deleted,
                    | make another address default.
                    */

                    if ($was_default) {

                        $stmt = $conn->prepare("
                            SELECT id
                            FROM addresses
                            WHERE user_id = ?
                            ORDER BY id DESC
                            LIMIT 1
                        ");

                        if ($stmt) {

                            $stmt->bind_param(
                                'i',
                                $customer_id
                            );

                            $stmt->execute();

                            $result = $stmt->get_result();

                            $new_default = $result->fetch_assoc();

                            $stmt->close();

                            if ($new_default) {

                                $new_default_id = (int) $new_default['id'];

                                $stmt = $conn->prepare("
                                    UPDATE addresses
                                    SET is_default = 1
                                    WHERE id = ?
                                      AND user_id = ?
                                ");

                                if (!$stmt) {
                                    throw new Exception();
                                }

                                $stmt->bind_param(
                                    'ii',
                                    $new_default_id,
                                    $customer_id
                                );

                                if (!$stmt->execute()) {
                                    throw new Exception();
                                }

                                $stmt->close();
                            }
                        }
                    }

                    $conn->commit();

                    $success_message = 'Address deleted successfully.';

                } catch (Throwable $e) {

                    $conn->rollback();

                    $errors[] = 'Unable to delete the address. Please try again.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Set Default Address
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    $action = $_POST['action'] ?? '';

    if ($action === 'set_default') {

        $address_id = (int) ($_POST['address_id'] ?? 0);

        if ($address_id <= 0) {

            $errors[] = 'Invalid address selected.';

        } else {

            $conn->begin_transaction();

            try {

                /*
                | Remove current default
                */

                $stmt = $conn->prepare("
                    UPDATE addresses
                    SET is_default = 0
                    WHERE user_id = ?
                ");

                if (!$stmt) {
                    throw new Exception();
                }

                $stmt->bind_param(
                    'i',
                    $customer_id
                );

                if (!$stmt->execute()) {
                    throw new Exception();
                }

                $stmt->close();

                /*
                | Set selected address as default
                | only if it belongs to customer
                */

                $stmt = $conn->prepare("
                    UPDATE addresses
                    SET is_default = 1
                    WHERE id = ?
                      AND user_id = ?
                ");

                if (!$stmt) {
                    throw new Exception();
                }

                $stmt->bind_param(
                    'ii',
                    $address_id,
                    $customer_id
                );

                if (!$stmt->execute() || $stmt->affected_rows === 0) {
                    throw new Exception();
                }

                $stmt->close();

                $conn->commit();

                $success_message = 'Default address updated successfully.';

            } catch (Throwable $e) {

                $conn->rollback();

                $errors[] = 'Unable to change the default address.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Customer Addresses
|--------------------------------------------------------------------------
*/

$addresses = [];

$stmt = $conn->prepare("
    SELECT
        id,
        address_label,
        address_line,
        landmark,
        city,
        state,
        postal_code,
        is_default
    FROM addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $customer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Customer name
|--------------------------------------------------------------------------
*/

$customer_name = $_SESSION['customer_name'] ?? 'Customer';

$initial = strtoupper(
    substr(
        trim($customer_name),
        0,
        1
    )
);

if ($initial === '') {
    $initial = 'C';
}

require_once __DIR__ . '/includes/customer_header.php';

?>

<div class="container py-4 py-lg-5">

    <!-- Page Header -->

    <div class="mb-4">

        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2">

            <i class="bi bi-person-circle me-1"></i>

            My Profile

        </span>

        <h1 class="fw-bold mt-3 mb-1">
            Hello, <?= htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p class="text-muted mb-0">
            Manage your profile and delivery addresses.
        </p>

    </div>


    <!-- Messages -->

    <?php if (!empty($success_message)): ?>

        <div class="alert alert-success border-0 shadow-sm">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars(
                $success_message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger border-0 shadow-sm">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <strong>Please fix the following:</strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="row g-4">

        <!-- Profile Card -->

        <div class="col-lg-4">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body p-4 text-center">

                    <div
                        class="rounded-circle bg-warning-subtle
                               d-inline-flex align-items-center
                               justify-content-center mb-3"
                        style="width:90px;height:90px;"
                    >

                        <span class="fs-1 fw-bold text-warning">
                            <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <h4 class="fw-bold mb-1">

                        <?= htmlspecialchars(
                            $customer_name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h4>

                    <p class="text-muted mb-4">
                        Smart Tiffin Customer
                    </p>


                    <div class="bg-light rounded-4 p-3 text-start">

                        <div class="d-flex align-items-center gap-3">

                            <div
                                class="rounded-circle bg-warning-subtle
                                       d-flex align-items-center
                                       justify-content-center"
                                style="width:42px;height:42px;"
                            >

                                <i class="bi bi-geo-alt text-warning"></i>

                            </div>

                            <div>

                                <div class="fw-semibold">
                                    Delivery Addresses
                                </div>

                                <small class="text-muted">

                                    <?= count($addresses) ?>

                                    <?= count($addresses) === 1
                                        ? 'address'
                                        : 'addresses' ?>

                                    saved

                                </small>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Quick Links -->

            <div class="card border-0 shadow-sm rounded-4 mt-4">

                <div class="card-body p-3">

                    <a
                        href="orders/index.php"
                        class="d-flex align-items-center gap-3
                               text-decoration-none text-dark
                               p-3 rounded-3"
                    >

                        <i class="bi bi-bag text-warning fs-5"></i>

                        <span class="fw-semibold">
                            My Orders
                        </span>

                        <i class="bi bi-chevron-right ms-auto text-muted"></i>

                    </a>


                    <a
                        href="tracking.php"
                        class="d-flex align-items-center gap-3
                               text-decoration-none text-dark
                               p-3 rounded-3"
                    >

                        <i class="bi bi-geo-alt text-warning fs-5"></i>

                        <span class="fw-semibold">
                            Track Delivery
                        </span>

                        <i class="bi bi-chevron-right ms-auto text-muted"></i>

                    </a>


                    <a
                        href="meals/index.php"
                        class="d-flex align-items-center gap-3
                               text-decoration-none text-dark
                               p-3 rounded-3"
                    >

                        <i class="bi bi-egg-fried text-warning fs-5"></i>

                        <span class="fw-semibold">
                            Browse Meals
                        </span>

                        <i class="bi bi-chevron-right ms-auto text-muted"></i>

                    </a>

                </div>

            </div>

        </div>


        <!-- Address Section -->

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body p-4 p-lg-5">

                    <div class="d-flex justify-content-between
                                align-items-center mb-4">

                        <div>

                            <h4 class="fw-bold mb-1">

                                <i class="bi bi-geo-alt text-warning me-2"></i>

                                Delivery Addresses

                            </h4>

                            <p class="text-muted mb-0">
                                Add the address where you want your tiffin delivered.
                            </p>

                        </div>

                    </div>


                    <!-- Add / Edit Address Form -->

                    <div class="bg-light rounded-4 p-4 mb-4">

                        <h5 class="fw-bold mb-4">

                            <i class="bi bi-<?= $edit_address ? 'pencil' : 'plus-circle' ?> text-warning me-2"></i>

                            <?= $edit_address
                                ? 'Edit Address'
                                : 'Add New Address' ?>

                        </h5>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrf_token,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="<?= $edit_address
                                    ? 'update'
                                    : 'add' ?>"
                            >

                            <?php if ($edit_address): ?>

                                <input
                                    type="hidden"
                                    name="address_id"
                                    value="<?= (int)$edit_address['id'] ?>"
                                >

                            <?php endif; ?>


                            <div class="row g-3">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Address Label
                                    </label>

                                    <select
                                        name="address_label"
                                        class="form-select"
                                        required
                                    >

                                        <?php

                                        $current_label =
                                            $edit_address['address_label']
                                            ?? '';

                                        ?>

                                        <option value="">
                                            Select Label
                                        </option>

                                        <option
                                            value="Home"
                                            <?= $current_label === 'Home'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Home
                                        </option>

                                        <option
                                            value="Office"
                                            <?= $current_label === 'Office'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Office
                                        </option>

                                        <option
                                            value="Hostel"
                                            <?= $current_label === 'Hostel'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Hostel
                                        </option>

                                        <option
                                            value="Other"
                                            <?= $current_label === 'Other'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Other
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Postal Code
                                    </label>

                                    <input
                                        type="text"
                                        name="postal_code"
                                        class="form-control"
                                        maxlength="6"
                                        inputmode="numeric"
                                        placeholder="e.g. 440001"
                                        value="<?= htmlspecialchars(
                                            $edit_address['postal_code']
                                                ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Complete Address
                                    </label>

                                    <textarea
                                        name="address_line"
                                        class="form-control"
                                        rows="3"
                                        maxlength="500"
                                        placeholder="House / Flat No., Building, Street, Area"
                                        required
                                    ><?= htmlspecialchars(
                                        $edit_address['address_line']
                                            ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?></textarea>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">

                                        Landmark

                                        <span class="text-muted fw-normal">
                                            (Optional)
                                        </span>

                                    </label>

                                    <input
                                        type="text"
                                        name="landmark"
                                        class="form-control"
                                        maxlength="150"
                                        placeholder="Near school, temple, mall..."
                                        value="<?= htmlspecialchars(
                                            $edit_address['landmark']
                                                ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        City
                                    </label>

                                    <input
                                        type="text"
                                        name="city"
                                        class="form-control"
                                        maxlength="100"
                                        placeholder="Nagpur"
                                        value="<?= htmlspecialchars(
                                            $edit_address['city']
                                                ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        State
                                    </label>

                                    <input
                                        type="text"
                                        name="state"
                                        class="form-control"
                                        maxlength="100"
                                        placeholder="Maharashtra"
                                        value="<?= htmlspecialchars(
                                            $edit_address['state']
                                                ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-12">

                                    <div class="form-check">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="is_default"
                                            id="is_default"
                                            <?= !empty($edit_address['is_default'])
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            class="form-check-label"
                                            for="is_default"
                                        >
                                            Make this my default delivery address
                                        </label>

                                    </div>

                                </div>


                                <div class="col-12">

                                    <div class="d-flex gap-2">

                                        <button
                                            type="submit"
                                            class="btn btn-warning
                                                   rounded-3 fw-bold"
                                        >

                                            <i class="bi bi-check-circle me-2"></i>

                                            <?= $edit_address
                                                ? 'Update Address'
                                                : 'Save Address' ?>

                                        </button>


                                        <?php if ($edit_address): ?>

                                            <a
                                                href="profile.php"
                                                class="btn btn-light
                                                       rounded-3"
                                            >
                                                Cancel
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>


                    <!-- Saved Addresses -->

                    <h5 class="fw-bold mb-3">

                        <i class="bi bi-bookmark-check text-warning me-2"></i>

                        Saved Addresses

                    </h5>


                    <?php if (empty($addresses)): ?>

                        <div class="text-center py-5">

                            <div
                                class="rounded-circle bg-warning-subtle
                                       d-inline-flex align-items-center
                                       justify-content-center mb-3"
                                style="width:80px;height:80px;"
                            >

                                <i class="bi bi-geo-alt fs-1 text-warning"></i>

                            </div>

                            <h5 class="fw-bold">
                                No addresses saved yet
                            </h5>

                            <p class="text-muted mb-0">
                                Add your delivery address above to place an order.
                            </p>

                        </div>

                    <?php else: ?>


                        <?php foreach ($addresses as $address): ?>

                            <div
                                class="border rounded-4 p-4 mb-3
                                       <?= !empty($address['is_default'])
                                           ? 'border-warning'
                                           : '' ?>"
                            >

                                <div
                                    class="d-flex justify-content-between
                                           align-items-start gap-3"
                                >

                                    <div class="d-flex gap-3">

                                        <div
                                            class="rounded-circle bg-warning-subtle
                                                   d-flex align-items-center
                                                   justify-content-center"
                                            style="width:45px;height:45px;"
                                        >

                                            <i class="bi bi-house text-warning"></i>

                                        </div>


                                        <div>

                                            <div class="d-flex align-items-center gap-2">

                                                <h6 class="fw-bold mb-0">

                                                    <?= htmlspecialchars(
                                                        $address['address_label']
                                                            ?: 'Delivery Address',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </h6>


                                                <?php if (!empty($address['is_default'])): ?>

                                                    <span
                                                        class="badge bg-warning-subtle
                                                               text-warning rounded-pill"
                                                    >
                                                        Default
                                                    </span>

                                                <?php endif; ?>

                                            </div>


                                            <p class="text-muted mb-1 mt-2">

                                                <?= htmlspecialchars(
                                                    $address['address_line'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                <?php if (!empty($address['landmark'])): ?>

                                                    <br>

                                                    <span>
                                                        <?= htmlspecialchars(
                                                            $address['landmark'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </span>

                                                <?php endif; ?>

                                                <br>

                                                <?= htmlspecialchars(
                                                    $address['city'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>,

                                                <?= htmlspecialchars(
                                                    $address['state'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                -

                                                <?= htmlspecialchars(
                                                    $address['postal_code'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </p>

                                        </div>

                                    </div>


                                    <div class="dropdown">

                                        <button
                                            class="btn btn-light btn-sm rounded-circle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                        >

                                            <i class="bi bi-three-dots-vertical"></i>

                                        </button>


                                        <ul class="dropdown-menu dropdown-menu-end">

                                            <li>

                                                <a
                                                    class="dropdown-item"
                                                    href="profile.php?edit=<?= (int)$address['id'] ?>"
                                                >

                                                    <i class="bi bi-pencil me-2"></i>

                                                    Edit

                                                </a>

                                            </li>


                                            <?php if (empty($address['is_default'])): ?>

                                                <li>

                                                    <form method="POST">

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars(
                                                                $csrf_token,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="set_default"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="address_id"
                                                            value="<?= (int)$address['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="dropdown-item"
                                                        >

                                                            <i class="bi bi-check-circle me-2"></i>

                                                            Set as Default

                                                        </button>

                                                    </form>

                                                </li>

                                            <?php endif; ?>


                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>


                                            <li>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this address?');"
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

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="address_id"
                                                        value="<?= (int)$address['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="dropdown-item text-danger"
                                                    >

                                                        <i class="bi bi-trash me-2"></i>

                                                        Delete

                                                    </button>

                                                </form>

                                            </li>

                                        </ul>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>


<?php
require_once __DIR__ . '/includes/customer_footer.php';
?>