<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Assign Delivery';
$active_page = 'deliveries';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_token'];

$error = '';
$success = '';

$edit_id = filter_input(
    INPUT_GET,
    'edit',
    FILTER_VALIDATE_INT
);

$orders = [];
$dabbawalas = [];
$current = null;


/*
 * Orders that don't already have an active assignment.
 */

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_number,
        u.name AS customer_name,
        o.delivery_date,
        o.delivery_time
    FROM orders o
    INNER JOIN users u
        ON o.user_id = u.id
    LEFT JOIN delivery_assignments da
        ON da.order_id = o.id
        AND da.assignment_status IN (
            'assigned',
            'accepted'
        )
    WHERE o.order_status IN (
        'pending',
        'confirmed'
    )
    AND da.id IS NULL
    ORDER BY o.delivery_date ASC, o.delivery_time ASC
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();
}


/*
 * Active dabbawalas.
 */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        phone,
        availability_status
    FROM dabbawalas
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dabbawalas[] = $row;
    }

    $stmt->close();
}


/*
 * Existing assignment for reassignment.
 */

if ($edit_id) {

    $stmt = $conn->prepare("
        SELECT
            id,
            order_id,
            dabbawala_id,
            assignment_status
        FROM delivery_assignments
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param('i', $edit_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $current = $result->fetch_assoc();

        $stmt->close();
    }
}


/*
 * Assignment POST.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error = 'Invalid request.';

    } else {

        $order_id = filter_var(
            $_POST['order_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $dabbawala_id = filter_var(
            $_POST['dabbawala_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $assignment_id = filter_var(
            $_POST['assignment_id'] ?? '',
            FILTER_VALIDATE_INT
        );


        if (!$order_id || !$dabbawala_id) {

            $error = 'Please select an order and dabbawala.';

        } else {

            /*
             * Prevent duplicate active assignment.
             */

            $stmt = $conn->prepare("
                SELECT id
                FROM delivery_assignments
                WHERE order_id = ?
                AND assignment_status IN ('assigned','accepted')
                AND (? = 0 OR id <> ?)
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param(
                    'iii',
                    $order_id,
                    $assignment_id,
                    $assignment_id
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $duplicate = $result->fetch_assoc();

                $stmt->close();


                if ($duplicate) {

                    $error =
                        'This order already has an active delivery assignment.';

                } else {

                    if ($assignment_id) {

                        $stmt = $conn->prepare("
                            UPDATE delivery_assignments
                            SET
                                dabbawala_id = ?,
                                assignment_status = 'assigned',
                                rejected_at = NULL,
                                rejection_reason = NULL
                            WHERE id = ?
                        ");

                        if ($stmt) {

                            $stmt->bind_param(
                                'ii',
                                $dabbawala_id,
                                $assignment_id
                            );

                            if ($stmt->execute()) {
                                $success =
                                    'Delivery reassigned successfully.';
                            } else {
                                $error =
                                    'Unable to reassign delivery.';
                            }

                            $stmt->close();
                        }

                    } else {

                        /*
                         * Existing session must contain admin ID.
                         */

                        $admin_id =
                            (int)(
                                $_SESSION['admin_id']
                                ?? $_SESSION['user_id']
                                ?? 0
                            );

                        if ($admin_id < 1) {

                            $error =
                                'Admin session ID is unavailable.';

                        } else {

                            $stmt = $conn->prepare("
                                INSERT INTO delivery_assignments
                                (
                                    order_id,
                                    dabbawala_id,
                                    assigned_by
                                )
                                VALUES (?, ?, ?)
                            ");

                            if ($stmt) {

                                $stmt->bind_param(
                                    'iii',
                                    $order_id,
                                    $dabbawala_id,
                                    $admin_id
                                );

                                if ($stmt->execute()) {

                                    $success =
                                        'Delivery assigned successfully.';

                                } else {

                                    $error =
                                        'Unable to assign delivery.';
                                }

                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <h2>
        <?php echo $edit_id ? 'Reassign Delivery' : 'Assign Delivery'; ?>
    </h2>

    <p class="text-muted">
        Assign an order to an active dabbawala.
    </p>

</div>


<?php if ($success): ?>

    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-danger">
        <?php echo e($error); ?>
    </div>

<?php endif; ?>


<div class="admin-data-card">

    <div class="card-body">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrf); ?>"
            >

            <input
                type="hidden"
                name="assignment_id"
                value="<?php echo (int)($current['id'] ?? 0); ?>"
            >


            <div class="mb-4">

                <label class="form-label fw-semibold">
                    Order
                </label>

                <select
                    name="order_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Order
                    </option>

                    <?php foreach ($orders as $order): ?>

                        <option
                            value="<?php echo (int)$order['id']; ?>"
                            <?php
                            echo (
                                (int)($current['order_id'] ?? 0)
                                ===
                                (int)$order['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo e(
                                $order['order_number']
                                . ' - '
                                . $order['customer_name']
                                . ' - '
                                . $order['delivery_date']
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="mb-4">

                <label class="form-label fw-semibold">
                    Dabbawala
                </label>

                <select
                    name="dabbawala_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Dabbawala
                    </option>

                    <?php foreach ($dabbawalas as $dabbawala): ?>

                        <option
                            value="<?php echo (int)$dabbawala['id']; ?>"
                            <?php
                            echo (
                                (int)($current['dabbawala_id'] ?? 0)
                                ===
                                (int)$dabbawala['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo e(
                                $dabbawala['name']
                                . ' - '
                                . $dabbawala['phone']
                                . ' - '
                                . ucfirst(
                                    $dabbawala['availability_status']
                                )
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>

                <?php echo $edit_id ? 'Reassign' : 'Assign Delivery'; ?>

            </button>

            <a
                href="index.php"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>