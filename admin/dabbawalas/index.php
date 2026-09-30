<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';


$page_title = 'Dabbawalas';
$active_page = 'dabbawalas';

$error_message = '';
$success_message = '';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');


// ==========================================================
// CSRF TOKEN
// ==========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


// ==========================================================
// HELPER
// ==========================================================

function clean_output($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ==========================================================
// HANDLE POST ACTIONS
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_token = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $posted_token
        )
    ) {

        $error_message = 'Invalid request. Please try again.';

    } else {

        $action = $_POST['action'] ?? '';

        // ==================================================
        // ADD DABBAWALA
        // ==================================================

        if ($action === 'add') {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $phone === '' || $password === '') {

                $error_message = 'All required fields must be filled.';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $error_message = 'Please enter a valid email address.';

            } elseif (strlen($password) < 6) {

                $error_message = 'Password must contain at least 6 characters.';

            } else {

                // Check duplicate email
                $check = $conn->prepare("
                    SELECT id
                    FROM dabbawalas
                    WHERE email = ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error_message = 'Unable to prepare the request.';

                } else {

                    $check->bind_param('s', $email);
                    $check->execute();

                    $result = $check->get_result();

                    if ($result && $result->num_rows > 0) {

                        $error_message = 'A dabbawala with this email already exists.';

                    } else {

                        $hashed_password = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $insert = $conn->prepare("
                            INSERT INTO dabbawalas
                                (
                                    name,
                                    email,
                                    phone,
                                    password
                                )
                            VALUES
                                (?, ?, ?, ?)
                        ");

                        if (!$insert) {

                            $error_message = 'Unable to prepare the insert request.';

                        } else {

                            $insert->bind_param(
                                'ssss',
                                $name,
                                $email,
                                $phone,
                                $hashed_password
                            );

                            if ($insert->execute()) {

                                $success_message = 'Dabbawala added successfully.';

                            } elseif ($conn->errno === 1062) {

                                $error_message = 'Email already exists.';

                            } else {

                                $error_message = 'Unable to add the dabbawala.';

                            }

                            $insert->close();
                        }
                    }

                    $check->close();
                }
            }
        }


        // ==================================================
        // EDIT DABBAWALA
        // ==================================================

        elseif ($action === 'edit') {

            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$id || $id < 1) {

                $error_message = 'Invalid dabbawala ID.';

            } elseif ($name === '' || $email === '' || $phone === '') {

                $error_message = 'Name, email and phone are required.';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $error_message = 'Please enter a valid email address.';

            } else {

                // Check email belongs to another dabbawala
                $check = $conn->prepare("
                    SELECT id
                    FROM dabbawalas
                    WHERE email = ?
                      AND id != ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error_message = 'Unable to prepare the request.';

                } else {

                    $check->bind_param(
                        'si',
                        $email,
                        $id
                    );

                    $check->execute();

                    $result = $check->get_result();

                    if ($result && $result->num_rows > 0) {

                        $error_message = 'Another dabbawala already uses this email.';

                    } else {

                        if ($password !== '') {

                            if (strlen($password) < 6) {

                                $error_message = 'Password must contain at least 6 characters.';

                            } else {

                                $hashed_password = password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );

                                $update = $conn->prepare("
                                    UPDATE dabbawalas
                                    SET
                                        name = ?,
                                        email = ?,
                                        phone = ?,
                                        password = ?
                                    WHERE id = ?
                                ");

                                if (!$update) {

                                    $error_message = 'Unable to prepare the update request.';

                                } else {

                                    $update->bind_param(
                                        'ssssi',
                                        $name,
                                        $email,
                                        $phone,
                                        $hashed_password,
                                        $id
                                    );

                                    if ($update->execute()) {

                                        $success_message = 'Dabbawala updated successfully.';

                                    } elseif ($conn->errno === 1062) {

                                        $error_message = 'Email already exists.';

                                    } else {

                                        $error_message = 'Unable to update the dabbawala.';

                                    }

                                    $update->close();
                                }
                            }

                        } else {

                            $update = $conn->prepare("
                                UPDATE dabbawalas
                                SET
                                    name = ?,
                                    email = ?,
                                    phone = ?
                                WHERE id = ?
                            ");

                            if (!$update) {

                                $error_message = 'Unable to prepare the update request.';

                            } else {

                                $update->bind_param(
                                    'sssi',
                                    $name,
                                    $email,
                                    $phone,
                                    $id
                                );

                                if ($update->execute()) {

                                    $success_message = 'Dabbawala updated successfully.';

                                } elseif ($conn->errno === 1062) {

                                    $error_message = 'Email already exists.';

                                } else {

                                    $error_message = 'Unable to update the dabbawala.';

                                }

                                $update->close();
                            }
                        }
                    }

                    $check->close();
                }
            }
        }


        // ==================================================
        // DELETE DABBAWALA
        // ==================================================

        elseif ($action === 'delete') {

            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {

                $error_message = 'Invalid dabbawala ID.';

            } else {

                $delete = $conn->prepare("
                    DELETE FROM dabbawalas
                    WHERE id = ?
                ");

                if (!$delete) {

                    $error_message = 'Unable to prepare the delete request.';

                } else {

                    $delete->bind_param('i', $id);

                    if ($delete->execute()) {

                        if ($delete->affected_rows > 0) {

                            $success_message = 'Dabbawala deleted successfully.';

                        } else {

                            $error_message = 'Dabbawala not found.';

                        }

                    } else {

                        if ($conn->errno === 1451) {

                            $error_message =
                                'This dabbawala cannot be deleted because related delivery records exist. Deactivate the account instead.';

                        } else {

                            $error_message =
                                'Unable to delete the dabbawala.';

                        }
                    }

                    $delete->close();
                }
            }
        }


        // ==================================================
        // ACTIVATE / DEACTIVATE
        // ==================================================

        elseif ($action === 'toggle_status') {

            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {

                $error_message = 'Invalid dabbawala ID.';

            } else {

                // Get current status
                $check = $conn->prepare("
                    SELECT status
                    FROM dabbawalas
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error_message = 'Unable to prepare the status request.';

                } else {

                    $check->bind_param('i', $id);
                    $check->execute();

                    $result = $check->get_result();

                    if (!$result || $result->num_rows === 0) {

                        $error_message = 'Dabbawala not found.';

                    } else {

                        $row = $result->fetch_assoc();

                        $new_status =
                            ($row['status'] === 'active')
                            ? 'inactive'
                            : 'active';


                        $update = $conn->prepare("
                            UPDATE dabbawalas
                            SET status = ?
                            WHERE id = ?
                        ");

                        if (!$update) {

                            $error_message = 'Unable to prepare the status update.';

                        } else {

                            $update->bind_param(
                                'si',
                                $new_status,
                                $id
                            );

                            if ($update->execute()) {

                                $success_message =
                                    'Dabbawala status updated successfully.';

                            } else {

                                $error_message =
                                    'Unable to update dabbawala status.';

                            }

                            $update->close();
                        }
                    }

                    $check->close();
                }
            }
        }
    }
}


// ==========================================================
// FETCH DABBAWALAS
// ==========================================================

$dabbawalas = [];


// Validate status filter against actual ENUM values
$allowed_statuses = [
    'active',
    'inactive'
];

if (
    $status_filter !== '' &&
    !in_array($status_filter, $allowed_statuses, true)
) {
    $status_filter = '';
}


$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        status,
        created_at
    FROM dabbawalas
    WHERE 1 = 1
";

$types = '';
$params = [];


// Search
if ($search !== '') {

    $sql .= "
        AND (
            name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $types .= 'sss';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
}


// Status filter
if ($status_filter !== '') {

    $sql .= " AND status = ? ";

    $types .= 's';

    $params[] = $status_filter;
}


$sql .= "ORDER BY created_at DESC";


$statement = $conn->prepare($sql);


if (!$statement) {

    $error_message = 'Unable to load dabbawala records.';

} else {

    if (!empty($params)) {

        $statement->bind_param(
            $types,
            ...$params
        );
    }

    if ($statement->execute()) {

        $result = $statement->get_result();

        if ($result) {

            while ($row = $result->fetch_assoc()) {

                $dabbawalas[] = $row;

            }

            $result->free();
        }

    } else {

        $error_message = 'Unable to load dabbawala records.';

    }

    $statement->close();
}


$dabbawala_count = count($dabbawalas);


// ==========================================================
// EXISTING ADMIN HEADER
// ==========================================================

require_once __DIR__ . '/../includes/admin_header.php';

?>


<!-- ======================================================
     PAGE HEADER
====================================================== -->

<div class="admin-page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h2>Dabbawalas</h2>

            <p class="text-muted">
                Manage delivery personnel registered in the system.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#addDabbawalaModal"
        >

            <i class="bi bi-person-plus me-2"></i>

            Add Dabbawala

        </button>

    </div>

</div>


<!-- ======================================================
     ALERTS
====================================================== -->

<?php if ($success_message !== ''): ?>

    <div class="alert alert-success alert-dismissible fade show">

        <i class="bi bi-check-circle me-2"></i>

        <?php echo clean_output($success_message); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<?php if ($error_message !== ''): ?>

    <div class="alert alert-danger alert-dismissible fade show">

        <i class="bi bi-exclamation-triangle me-2"></i>

        <?php echo clean_output($error_message); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!-- ======================================================
     SEARCH + FILTER
====================================================== -->

<div class="admin-data-card mb-4">

    <div class="card-body">

        <form
            method="GET"
            action=""
            class="row g-3 align-items-end"
        >

            <div class="col-md-6">

                <label class="form-label">
                    Search Dabbawalas
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search by name, email or phone"
                    value="<?php echo clean_output($search); ?>"
                >

            </div>


            <div class="col-md-4">

                <label class="form-label">
                    Status
                </label>

                <select
                    name="status"
                    class="form-select"
                >

                    <option value="">
                        All
                    </option>

                    <option
                        value="active"
                        <?php echo $status_filter === 'active' ? 'selected' : ''; ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <div class="col-md-2 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >

                    <i class="bi bi-search"></i>

                    Search

                </button>


                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                    title="Clear"
                >

                    <i class="bi bi-arrow-counterclockwise"></i>

                </a>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================
     DABBAWALA LIST
====================================================== -->

<div class="admin-data-card">

    <div class="admin-data-card-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5>
                    Dabbawala List
                </h5>

                <small class="text-muted">

                    <?php echo number_format($dabbawala_count); ?>

                    result<?php echo $dabbawala_count === 1 ? '' : 's'; ?>

                </small>

            </div>


            <span class="badge bg-warning text-dark">

                <i class="bi bi-bicycle me-1"></i>

                <?php echo number_format($dabbawala_count); ?>

            </span>

        </div>

    </div>


    <div class="card-body p-0">


        <?php if (!empty($dabbawalas)): ?>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                ID
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th class="text-center">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($dabbawalas as $dabbawala): ?>

                            <tr>

                                <td class="px-4">

                                    <strong>
                                        <?php echo (int) $dabbawala['id']; ?>
                                    </strong>

                                </td>


                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <div
                                            class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width:40px;height:40px;"
                                        >

                                            <i class="bi bi-bicycle text-warning"></i>

                                        </div>

                                        <div>

                                            <div class="fw-semibold">

                                                <?php
                                                echo clean_output(
                                                    $dabbawala['name']
                                                );
                                                ?>

                                            </div>

                                            <small class="text-muted">
                                                Dabbawala
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo clean_output(
                                        $dabbawala['email']
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo clean_output(
                                        $dabbawala['phone']
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php if ($dabbawala['status'] === 'active'): ?>

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>
                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <i class="bi bi-pause-circle me-1"></i>
                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php
                                    echo clean_output(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $dabbawala['created_at']
                                            )
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <div class="d-flex justify-content-center gap-1">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editDabbawalaModal"
                                            data-id="<?php echo (int) $dabbawala['id']; ?>"
                                            data-name="<?php echo clean_output($dabbawala['name']); ?>"
                                            data-email="<?php echo clean_output($dabbawala['email']); ?>"
                                            data-phone="<?php echo clean_output($dabbawala['phone']); ?>"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </button>


                                        <!-- STATUS -->

                                        <form
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to change this dabbawala status?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo clean_output($csrf_token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $dabbawala['id']; ?>"
                                            >

                                            <?php if ($dabbawala['status'] === 'active'): ?>

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-warning"
                                                    title="Deactivate"
                                                >

                                                    <i class="bi bi-pause-circle"></i>

                                                </button>

                                            <?php else: ?>

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Activate"
                                                >

                                                    <i class="bi bi-check-circle"></i>

                                                </button>

                                            <?php endif; ?>

                                        </form>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this dabbawala? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo clean_output($csrf_token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $dabbawala['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete"
                                            >

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="admin-empty-state">

                <div class="admin-empty-state-icon">

                    <i class="bi bi-bicycle"></i>

                </div>

                <h5 class="fw-bold">
                    No dabbawalas found
                </h5>

                <p class="text-muted mb-0">

                    <?php if ($search !== '' || $status_filter !== ''): ?>

                        No records match your search or filter.

                    <?php else: ?>

                        There are currently no registered dabbawalas.

                    <?php endif; ?>

                </p>

            </div>


        <?php endif; ?>

    </div>

</div>


<!-- ======================================================
     ADD DABBAWALA MODAL
====================================================== -->

<div
    class="modal fade"
    id="addDabbawalaModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">
                    Add Dabbawala
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo clean_output($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >


                    <div class="mb-3">

                        <label class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Phone <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            maxlength="20"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="6"
                            required
                        >

                        <small class="text-muted">
                            Minimum 6 characters.
                        </small>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-person-plus me-1"></i>

                        Add Dabbawala

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ======================================================
     EDIT DABBAWALA MODAL
====================================================== -->

<div
    class="modal fade"
    id="editDabbawalaModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">
                    Edit Dabbawala
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo clean_output($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="edit"
                    >

                    <input
                        type="hidden"
                        name="id"
                        id="edit_dabbawala_id"
                    >


                    <div class="mb-3">

                        <label class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="edit_dabbawala_name"
                            class="form-control"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="edit_dabbawala_email"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Phone <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="phone"
                            id="edit_dabbawala_phone"
                            class="form-control"
                            maxlength="20"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="6"
                        >

                        <small class="text-muted">
                            Leave blank to keep the current password.
                        </small>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-save me-1"></i>

                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const editModal =
        document.getElementById('editDabbawalaModal');

    if (!editModal) {
        return;
    }

    editModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button = event.relatedTarget;

            document.getElementById('edit_dabbawala_id').value =
                button.getAttribute('data-id');

            document.getElementById('edit_dabbawala_name').value =
                button.getAttribute('data-name');

            document.getElementById('edit_dabbawala_email').value =
                button.getAttribute('data-email');

            document.getElementById('edit_dabbawala_phone').value =
                button.getAttribute('data-phone');

        }
    );

});
</script>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>