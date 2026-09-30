<?php

require_once __DIR__ . '/includes/dabbawala_auth.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'My Profile';
$active_page = 'profile';

$dabbawala_id = (int) $dabbawala_id;

$success_message = '';
$error_message = '';

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
| UPDATE PROFILE / AVAILABILITY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_token = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $posted_token
        )
    ) {

        $error_message = 'Invalid security token. Please try again.';

    } else {

        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | UPDATE PERSONAL INFORMATION
        |--------------------------------------------------------------------------
        */

        if ($action === 'update_profile') {

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($name === '') {

                $error_message = 'Name is required.';

            } elseif (strlen($name) < 2) {

                $error_message = 'Name must contain at least 2 characters.';

            } elseif ($phone === '') {

                $error_message = 'Phone number is required.';

            } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {

                $error_message = 'Please enter a valid phone number.';

            } else {

                $sql = "
                    UPDATE dabbawalas
                    SET
                        name = ?,
                        phone = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {

                    $stmt->bind_param(
                        "ssi",
                        $name,
                        $phone,
                        $dabbawala_id
                    );

                    if ($stmt->execute()) {

                        $success_message =
                            'Profile updated successfully.';

                        /*
                        |--------------------------------------------------------------------------
                        | Update session values
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['dabbawala_name'] = $name;

                        $dabbawala_name = $name;

                    } else {

                        $error_message =
                            'Unable to update profile. Please try again.';
                    }

                    $stmt->close();

                } else {

                    $error_message =
                        'Unable to prepare the profile update.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE AVAILABILITY
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update_availability') {

            $availability =
                trim($_POST['availability_status'] ?? '');

            $allowed_statuses = [
                'available',
                'busy',
                'unavailable'
            ];

            if (!in_array($availability, $allowed_statuses, true)) {

                $error_message =
                    'Invalid availability status.';

            } else {

                $sql = "
                    UPDATE dabbawalas
                    SET
                        availability_status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {

                    $stmt->bind_param(
                        "si",
                        $availability,
                        $dabbawala_id
                    );

                    if ($stmt->execute()) {

                        $success_message =
                            'Availability updated successfully.';

                    } else {

                        $error_message =
                            'Unable to update availability.';
                    }

                    $stmt->close();

                } else {

                    $error_message =
                        'Unable to prepare availability update.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET CURRENT DABBAWALA DATA
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        profile_image,
        availability_status,
        status,
        created_at
    FROM dabbawalas
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$dabbawala = null;

if ($stmt) {

    $stmt->bind_param("i", $dabbawala_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $dabbawala = $result->fetch_assoc();

    $stmt->close();
}

if (!$dabbawala) {

    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}


function dabbawala_profile_e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| STATUS HELPERS
|--------------------------------------------------------------------------
*/

$availability = strtolower(
    (string) $dabbawala['availability_status']
);

$account_status = strtolower(
    (string) $dabbawala['status']
);

$availability_class = 'bg-secondary-subtle text-secondary';

if ($availability === 'available') {
    $availability_class = 'bg-success-subtle text-success';
} elseif ($availability === 'busy') {
    $availability_class = 'bg-warning-subtle text-warning-emphasis';
} elseif ($availability === 'unavailable') {
    $availability_class = 'bg-danger-subtle text-danger';
}

$account_class =
    $account_status === 'active'
        ? 'bg-success-subtle text-success'
        : 'bg-danger-subtle text-danger';


require_once __DIR__ . '/includes/dabbawala_header.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="mb-4">

        <div class="text-muted small mb-1">
            <i class="bi bi-person-circle me-1"></i>
            Account Settings
        </div>

        <h2 class="fw-bold mb-1">
            My Profile
        </h2>

        <p class="text-muted mb-0">
            Manage your personal information and delivery availability.
        </p>

    </div>


    <!-- Alerts -->

    <?php if ($success_message !== ''): ?>

        <div class="alert alert-success alert-dismissible fade show rounded-4">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?php echo dabbawala_profile_e($success_message); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($error_message !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show rounded-4">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?php echo dabbawala_profile_e($error_message); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <div class="row g-4">

        <!-- Profile Card -->
        <div class="col-lg-8">

            <div class="bg-white rounded-4 shadow-sm p-4">

                <div class="d-flex align-items-center gap-3 mb-4">

                    <div
                        class="rounded-circle d-flex align-items-center justify-content-center"
                        style="
                            width:72px;
                            height:72px;
                            background:#fff3e8;
                            color:#ff6b00;
                        "
                    >
                        <i class="bi bi-person-fill fs-2"></i>
                    </div>

                    <div>

                        <h4 class="fw-bold mb-1">
                            Personal Information
                        </h4>

                        <p class="text-muted mb-0">
                            Update your basic account information.
                        </p>

                    </div>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo dabbawala_profile_e($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >


                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Full Name
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?php
                                    echo dabbawala_profile_e(
                                        $dabbawala['name']
                                    );
                                ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Email
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>

                            <input
                                type="email"
                                class="form-control"
                                value="<?php
                                    echo dabbawala_profile_e(
                                        $dabbawala['email']
                                    );
                                ?>"
                                readonly
                            >

                        </div>

                        <small class="text-muted">
                            Email is managed by the account system.
                        </small>

                    </div>


                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Phone Number
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-telephone"></i>
                            </span>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="<?php
                                    echo dabbawala_profile_e(
                                        $dabbawala['phone']
                                    );
                                ?>"
                                maxlength="20"
                                required
                            >

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="btn px-4 py-2 text-white fw-semibold"
                        style="background:#ff6b00;"
                    >

                        <i class="bi bi-check2-circle me-1"></i>

                        Save Changes

                    </button>

                </form>

            </div>

        </div>


        <!-- Status Card -->
        <div class="col-lg-4">

            <div class="bg-white rounded-4 shadow-sm p-4 mb-4">

                <h5 class="fw-bold mb-4">
                    <i class="bi bi-shield-check me-2"></i>
                    Account Status
                </h5>


                <div class="d-flex justify-content-between align-items-center mb-3">

                    <span class="text-muted">
                        Account
                    </span>

                    <span class="badge rounded-pill px-3 py-2 <?php
                        echo $account_class;
                    ?>">
                        <?php
                        echo ucfirst(
                            dabbawala_profile_e($account_status)
                        );
                        ?>
                    </span>

                </div>


                <div class="d-flex justify-content-between align-items-center">

                    <span class="text-muted">
                        Member Since
                    </span>

                    <span class="fw-semibold">
                        <?php
                        echo !empty($dabbawala['created_at'])
                            ? date(
                                'd M Y',
                                strtotime($dabbawala['created_at'])
                            )
                            : '—';
                        ?>
                    </span>

                </div>

            </div>


            <!-- Availability -->
            <div class="bg-white rounded-4 shadow-sm p-4">

                <h5 class="fw-bold mb-2">
                    <i class="bi bi-broadcast me-2"></i>
                    Availability
                </h5>

                <p class="text-muted small mb-4">
                    Tell the system whether you are currently available
                    for deliveries.
                </p>


                <div class="text-center mb-4">

                    <span
                        class="badge rounded-pill px-4 py-2 <?php
                            echo $availability_class;
                        ?>"
                    >
                        <i class="bi bi-circle-fill me-1"></i>

                        <?php
                        echo ucfirst(
                            dabbawala_profile_e(
                                str_replace(
                                    '_',
                                    ' ',
                                    $availability
                                )
                            )
                        );
                        ?>

                    </span>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo dabbawala_profile_e($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_availability"
                    >


                    <label class="form-label fw-semibold">
                        Change Availability
                    </label>

                    <select
                        name="availability_status"
                        class="form-select mb-3"
                        required
                    >

                        <option
                            value="available"
                            <?php
                            echo $availability === 'available'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Available
                        </option>

                        <option
                            value="busy"
                            <?php
                            echo $availability === 'busy'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Busy
                        </option>

                        <option
                            value="unavailable"
                            <?php
                            echo $availability === 'unavailable'
                                ? 'selected'
                                : '';
                            ?>
                        >
                            Unavailable
                        </option>

                    </select>


                    <button
                        type="submit"
                        class="btn w-100 fw-semibold"
                        style="
                            background:#fff3e8;
                            color:#ff6b00;
                            border:1px solid #ffd9bd;
                        "
                    >

                        <i class="bi bi-arrow-repeat me-1"></i>

                        Update Availability

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/includes/dabbawala_footer.php';
?>