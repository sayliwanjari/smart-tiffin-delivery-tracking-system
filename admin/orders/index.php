<?php
/**
 * Smart Tiffin Delivery & Tracking System
 * Admin - Order Management
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Orders';
$active_page = 'orders';

$error_message = '';
$success_message = '';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$view_id = filter_var($_GET['view'] ?? '', FILTER_VALIDATE_INT);


// ==========================================================
// CSRF
// ==========================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


// ==========================================================
// HELPER FUNCTIONS
// ==========================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function orderStatusClass($status)
{
    switch ($status) {

        case 'pending':
            return 'bg-warning text-dark';

        case 'confirmed':
            return 'bg-primary';

        case 'completed':
            return 'bg-success';

        case 'cancelled':
            return 'bg-danger';

        default:
            return 'bg-secondary';
    }
}


function orderStatusLabel($status)
{
    return ucwords(
        str_replace('_', ' ', $status)
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
        // UPDATE ORDER STATUS
        // ==================================================

        if ($action === 'update_status') {

            $order_id = filter_var(
                $_POST['order_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $new_status = trim(
                $_POST['order_status'] ?? ''
            );

            $allowed_statuses = [
                'pending',
                'confirmed',
                'cancelled',
                'completed'
            ];

            if (!$order_id || $order_id < 1) {

                $error_message = 'Invalid order ID.';

            } elseif (
                !in_array(
                    $new_status,
                    $allowed_statuses,
                    true
                )
            ) {

                $error_message = 'Invalid order status.';

            } else {

                $statement = $conn->prepare("
                    UPDATE orders
                    SET order_status = ?
                    WHERE id = ?
                ");

                if (!$statement) {

                    $error_message =
                        'Unable to update order status.';

                } else {

                    $statement->bind_param(
                        'si',
                        $new_status,
                        $order_id
                    );

                    if ($statement->execute()) {

                        if ($statement->affected_rows >= 0) {

                            $success_message =
                                'Order status updated successfully.';

                        }

                    } else {

                        $error_message =
                            'Unable to update order status.';
                    }

                    $statement->close();
                }
            }
        }


        // ==================================================
        // DELETE ORDER
        // ==================================================

        elseif ($action === 'delete') {

            $order_id = filter_var(
                $_POST['order_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$order_id || $order_id < 1) {

                $error_message = 'Invalid order ID.';

            } else {

                /*
                 * Payments and delivery assignments use
                 * ON DELETE RESTRICT.
                 *
                 * Check them before attempting deletion.
                 */

                $dependency_statement = $conn->prepare("
                    SELECT
                        (
                            SELECT COUNT(*)
                            FROM payments
                            WHERE order_id = ?
                        ) AS payment_count,

                        (
                            SELECT COUNT(*)
                            FROM delivery_assignments
                            WHERE order_id = ?
                        ) AS assignment_count
                ");

                if (!$dependency_statement) {

                    $error_message =
                        'Unable to check order dependencies.';

                } else {

                    $dependency_statement->bind_param(
                        'ii',
                        $order_id,
                        $order_id
                    );

                    $dependency_statement->execute();

                    $dependency_result =
                        $dependency_statement->get_result();

                    $dependency =
                        $dependency_result->fetch_assoc();

                    $dependency_statement->close();


                    $payment_count =
                        (int) ($dependency['payment_count'] ?? 0);

                    $assignment_count =
                        (int) ($dependency['assignment_count'] ?? 0);


                    if (
                        $payment_count > 0 ||
                        $assignment_count > 0
                    ) {

                        $error_message =
                            'This order cannot be deleted because it has related payment or delivery records.';

                    } else {

                        $delete_statement = $conn->prepare("
                            DELETE FROM orders
                            WHERE id = ?
                        ");

                        if (!$delete_statement) {

                            $error_message =
                                'Unable to prepare order deletion.';

                        } else {

                            $delete_statement->bind_param(
                                'i',
                                $order_id
                            );

                            if ($delete_statement->execute()) {

                                if (
                                    $delete_statement->affected_rows > 0
                                ) {

                                    $success_message =
                                        'Order deleted successfully.';

                                } else {

                                    $error_message =
                                        'Order not found.';
                                }

                            } else {

                                if ($conn->errno === 1451) {

                                    $error_message =
                                        'This order cannot be deleted because related records exist.';

                                } else {

                                    $error_message =
                                        'Unable to delete the order.';
                                }
                            }

                            $delete_statement->close();
                        }
                    }
                }
            }
        }
    }
}


// ==========================================================
// SUMMARY COUNTS
// ==========================================================

$total_orders = 0;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;


// Total
$statement = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($statement) {

    $statement->execute();

    $result = $statement->get_result();

    if ($row = $result->fetch_assoc()) {

        $total_orders = (int) $row['total'];
    }

    $statement->close();
}


// Pending
$statement = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE order_status = 'pending'
");

if ($statement) {

    $statement->execute();

    $result = $statement->get_result();

    if ($row = $result->fetch_assoc()) {

        $pending_orders = (int) $row['total'];
    }

    $statement->close();
}


// Completed
$statement = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE order_status = 'completed'
");

if ($statement) {

    $statement->execute();

    $result = $statement->get_result();

    if ($row = $result->fetch_assoc()) {

        $completed_orders = (int) $row['total'];
    }

    $statement->close();
}


// Cancelled
$statement = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE order_status = 'cancelled'
");

if ($statement) {

    $statement->execute();

    $result = $statement->get_result();

    if ($row = $result->fetch_assoc()) {

        $cancelled_orders = (int) $row['total'];
    }

    $statement->close();
}


// ==========================================================
// SEARCH + FILTER
// ==========================================================

$allowed_statuses = [
    'pending',
    'confirmed',
    'cancelled',
    'completed'
];

if (
    $status_filter !== '' &&
    !in_array(
        $status_filter,
        $allowed_statuses,
        true
    )
) {

    $status_filter = '';
}


$sql = "
    SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.order_status,
        o.delivery_date,
        o.delivery_time,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone

    FROM orders AS o

    INNER JOIN users AS u
        ON o.user_id = u.id

    WHERE 1 = 1
";


$types = '';
$params = [];


// Search order number/customer
if ($search !== '') {

    $sql .= "
        AND (
            o.order_number LIKE ?
            OR u.name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $types .= 'ssss';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
}


// Status
if ($status_filter !== '') {

    $sql .= "
        AND o.order_status = ?
    ";

    $types .= 's';

    $params[] = $status_filter;
}


$sql .= "
    ORDER BY o.created_at DESC
";


$orders = [];

$statement = $conn->prepare($sql);

if (!$statement) {

    $error_message =
        'Unable to load orders.';

} else {

    if (!empty($params)) {

        $statement->bind_param(
            $types,
            ...$params
        );
    }

    if ($statement->execute()) {

        $result = $statement->get_result();

        while ($row = $result->fetch_assoc()) {

            $orders[] = $row;
        }

        $result->free();

    } else {

        $error_message =
            'Unable to load orders.';
    }

    $statement->close();
}


// ==========================================================
// VIEW ORDER DETAILS
// ==========================================================

$view_order = null;
$view_items = [];
$view_payments = [];
$view_assignments = [];

if ($view_id && $view_id > 0) {

    // ----------------------------------------------
    // Order + Customer
    // ----------------------------------------------

    $statement = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.subscription_id,
            o.address_id,
            o.delivery_date,
            o.delivery_time,
            o.subtotal,
            o.delivery_fee,
            o.total_amount,
            o.order_status,
            o.notes,
            o.created_at,
            o.updated_at,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone

        FROM orders AS o

        INNER JOIN users AS u
            ON o.user_id = u.id

        WHERE o.id = ?

        LIMIT 1
    ");

    if ($statement) {

        $statement->bind_param(
            'i',
            $view_id
        );

        $statement->execute();

        $result = $statement->get_result();

        $view_order = $result->fetch_assoc();

        $statement->close();
    }


    if ($view_order) {

        // ------------------------------------------
        // Order Items + Meals
        // ------------------------------------------

        $statement = $conn->prepare("
            SELECT
                oi.id,
                oi.quantity,
                oi.unit_price,
                oi.subtotal,
                m.name AS meal_name

            FROM order_items AS oi

            INNER JOIN meals AS m
                ON oi.meal_id = m.id

            WHERE oi.order_id = ?

            ORDER BY oi.id ASC
        ");

        if ($statement) {

            $statement->bind_param(
                'i',
                $view_id
            );

            $statement->execute();

            $result = $statement->get_result();

            while ($row = $result->fetch_assoc()) {

                $view_items[] = $row;
            }

            $statement->close();
        }


        // ------------------------------------------
        // Payments
        // ------------------------------------------

        $statement = $conn->prepare("
            SELECT
                id,
                payment_reference,
                amount,
                payment_method,
                payment_status,
                transaction_reference,
                paid_at,
                created_at

            FROM payments

            WHERE order_id = ?

            ORDER BY created_at DESC
        ");

        if ($statement) {

            $statement->bind_param(
                'i',
                $view_id
            );

            $statement->execute();

            $result = $statement->get_result();

            while ($row = $result->fetch_assoc()) {

                $view_payments[] = $row;
            }

            $statement->close();
        }


        // ------------------------------------------
        // Delivery Assignments
        // ------------------------------------------

        $statement = $conn->prepare("
            SELECT
                da.id,
                da.assigned_at,
                da.accepted_at,
                da.rejected_at,
                da.completed_at,
                da.assignment_status,
                d.name AS dabbawala_name,
                d.phone AS dabbawala_phone

            FROM delivery_assignments AS da

            INNER JOIN dabbawalas AS d
                ON da.dabbawala_id = d.id

            WHERE da.order_id = ?

            ORDER BY da.id DESC
        ");

        if ($statement) {

            $statement->bind_param(
                'i',
                $view_id
            );

            $statement->execute();

            $result = $statement->get_result();

            while ($row = $result->fetch_assoc()) {

                $view_assignments[] = $row;
            }

            $statement->close();
        }
    }
}


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

        <h2 class="fw-bold mb-1">
            Orders
        </h2>

        <p class="text-muted mb-0">
            View and manage customer orders.
        </p>

    </div>

    <div>

        <a
            href="add.php"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-circle me-2"></i>
            Add Order
        </a>

    </div>

</div>

<!-- ======================================================
     ALERTS
====================================================== -->

<?php if ($success_message !== ''): ?>

    <div class="alert alert-success alert-dismissible fade show">

        <i class="bi bi-check-circle me-2"></i>

        <?php echo e($success_message); ?>

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

        <?php echo e($error_message); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!-- ======================================================
     SUMMARY CARDS
====================================================== -->

<div class="row g-4 mb-4">


    <!-- Total -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between">

                    <div>

                        <p class="text-muted mb-2">
                            Total Orders
                        </p>

                        <h3 class="fw-bold mb-0">
                            <?php echo number_format($total_orders); ?>
                        </h3>

                    </div>

                    <div
                        class="rounded-3 bg-primary-subtle d-flex align-items-center justify-content-center"
                        style="width:50px;height:50px;"
                    >

                        <i class="bi bi-cart3 fs-4 text-primary"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Pending -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between">

                    <div>

                        <p class="text-muted mb-2">
                            Pending
                        </p>

                        <h3 class="fw-bold mb-0">
                            <?php echo number_format($pending_orders); ?>
                        </h3>

                    </div>

                    <div
                        class="rounded-3 bg-warning-subtle d-flex align-items-center justify-content-center"
                        style="width:50px;height:50px;"
                    >

                        <i class="bi bi-clock fs-4 text-warning"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Completed -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between">

                    <div>

                        <p class="text-muted mb-2">
                            Completed
                        </p>

                        <h3 class="fw-bold mb-0">
                            <?php echo number_format($completed_orders); ?>
                        </h3>

                    </div>

                    <div
                        class="rounded-3 bg-success-subtle d-flex align-items-center justify-content-center"
                        style="width:50px;height:50px;"
                    >

                        <i class="bi bi-check-circle fs-4 text-success"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Cancelled -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between">

                    <div>

                        <p class="text-muted mb-2">
                            Cancelled
                        </p>

                        <h3 class="fw-bold mb-0">
                            <?php echo number_format($cancelled_orders); ?>
                        </h3>

                    </div>

                    <div
                        class="rounded-3 bg-danger-subtle d-flex align-items-center justify-content-center"
                        style="width:50px;height:50px;"
                    >

                        <i class="bi bi-x-circle fs-4 text-danger"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ======================================================
     SEARCH / FILTER
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
                    Search Orders
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Order number, customer name, email or phone"
                    value="<?php echo e($search); ?>"
                >

            </div>


            <div class="col-md-4">

                <label class="form-label">
                    Order Status
                </label>

                <select
                    name="status"
                    class="form-select"
                >

                    <option value="">
                        All
                    </option>

                    <?php foreach ($allowed_statuses as $status): ?>

                        <option
                            value="<?php echo e($status); ?>"
                            <?php
                            echo $status_filter === $status
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo e(
                                orderStatusLabel($status)
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

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
     ORDERS TABLE
====================================================== -->

<div class="admin-data-card">

    <div class="admin-data-card-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5>
                    Order List
                </h5>

                <small class="text-muted">

                    <?php echo number_format(count($orders)); ?>

                    result<?php echo count($orders) === 1 ? '' : 's'; ?>

                </small>

            </div>

            <span class="badge bg-primary">

                <i class="bi bi-cart3 me-1"></i>

                <?php echo number_format($total_orders); ?>

            </span>

        </div>

    </div>


    <div class="card-body p-0">


        <?php if (!empty($orders)): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                Order
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Delivery Date
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

                        <?php foreach ($orders as $order): ?>

                            <tr>

                                <td class="px-4">

                                    <span class="fw-semibold">

                                        <?php
                                        echo e(
                                            $order['order_number']
                                        );
                                        ?>

                                    </span>

                                    <div class="small text-muted">
                                        #<?php echo (int) $order['id']; ?>
                                    </div>

                                </td>


                                <td>

                                    <div class="fw-semibold">

                                        <?php
                                        echo e(
                                            $order['customer_name']
                                        );
                                        ?>

                                    </div>

                                    <small class="text-muted">

                                        <?php
                                        echo e(
                                            $order['customer_email']
                                        );
                                        ?>

                                    </small>

                                </td>


                                <td>

                                    <strong>
                                        ₹<?php
                                        echo number_format(
                                            (float) $order['total_amount'],
                                            2
                                        );
                                        ?>
                                    </strong>

                                </td>


                                <td>

                                    <?php
                                    echo e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $order['delivery_date']
                                            )
                                        )
                                    );
                                    ?>

                                    <div class="small text-muted">

                                        <?php
                                        echo e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $order['delivery_time']
                                                )
                                            )
                                        );
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="badge <?php
                                        echo orderStatusClass(
                                            $order['order_status']
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            orderStatusLabel(
                                                $order['order_status']
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="text-muted">

                                        <?php
                                        echo e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="d-flex justify-content-center gap-1">


                                        <!-- VIEW -->

                                        <a
                                            href="index.php?view=<?php echo (int) $order['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Order"
                                        >

                                            <i class="bi bi-eye"></i>

                                        </a>


                                        <!-- STATUS -->

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-warning"
                                            data-bs-toggle="modal"
                                            data-bs-target="#statusModal"
                                            data-id="<?php echo (int) $order['id']; ?>"
                                            data-order="<?php echo e($order['order_number']); ?>"
                                            data-status="<?php echo e($order['order_status']); ?>"
                                            title="Update Status"
                                        >

                                            <i class="bi bi-arrow-repeat"></i>

                                        </button>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($csrf_token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php echo (int) $order['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete Order"
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

                    <i class="bi bi-cart3"></i>

                </div>

                <h5 class="fw-bold">
                    No orders found
                </h5>

                <p class="text-muted mb-0">

                    <?php if ($search !== '' || $status_filter !== ''): ?>

                        No orders match your search or filter.

                    <?php else: ?>

                        There are currently no orders in the system.

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- ======================================================
     STATUS MODAL
====================================================== -->

<div
    class="modal fade"
    id="statusModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Update Order Status
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
                        value="<?php echo e($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_status"
                    >

                    <input
                        type="hidden"
                        name="order_id"
                        id="status_order_id"
                    >


                    <div class="mb-3">

                        <label class="form-label">
                            Order
                        </label>

                        <input
                            type="text"
                            id="status_order_number"
                            class="form-control"
                            readonly
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Order Status
                        </label>

                        <select
                            name="order_status"
                            id="status_order_status"
                            class="form-select"
                            required
                        >

                            <?php foreach ($allowed_statuses as $status): ?>

                                <option
                                    value="<?php echo e($status); ?>"
                                >

                                    <?php
                                    echo e(
                                        orderStatusLabel($status)
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

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

                        Update Status

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ======================================================
     VIEW ORDER MODAL
====================================================== -->

<?php if ($view_order): ?>

<div
    class="modal fade"
    id="viewOrderModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">


            <div class="modal-header">

                <div>

                    <h5 class="modal-title">

                        Order
                        <?php echo e($view_order['order_number']); ?>

                    </h5>

                    <small class="text-muted">

                        Created
                        <?php
                        echo e(
                            date(
                                'd M Y, h:i A',
                                strtotime(
                                    $view_order['created_at']
                                )
                            )
                        );
                        ?>

                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <!-- Customer -->

                <div class="card border-0 bg-light mb-4">

                    <div class="card-body">

                        <h6 class="fw-bold mb-3">
                            Customer Information
                        </h6>

                        <div class="row g-3">

                            <div class="col-md-4">

                                <small class="text-muted">
                                    Name
                                </small>

                                <div class="fw-semibold">
                                    <?php echo e($view_order['customer_name']); ?>
                                </div>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Email
                                </small>

                                <div>
                                    <?php echo e($view_order['customer_email']); ?>
                                </div>

                            </div>


                            <div class="col-md-4">

                                <small class="text-muted">
                                    Phone
                                </small>

                                <div>
                                    <?php echo e($view_order['customer_phone']); ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Order Information -->

                <div class="row g-4 mb-4">

                    <div class="col-md-6">

                        <div class="card h-100">

                            <div class="card-body">

                                <h6 class="fw-bold mb-3">
                                    Order Information
                                </h6>

                                <p class="mb-2">

                                    <strong>Order:</strong>

                                    <?php
                                    echo e(
                                        $view_order['order_number']
                                    );
                                    ?>

                                </p>


                                <p class="mb-2">

                                    <strong>Status:</strong>

                                    <span
                                        class="badge <?php
                                        echo orderStatusClass(
                                            $view_order['order_status']
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            orderStatusLabel(
                                                $view_order['order_status']
                                            )
                                        );
                                        ?>

                                    </span>

                                </p>


                                <p class="mb-2">

                                    <strong>Delivery Date:</strong>

                                    <?php
                                    echo e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $view_order['delivery_date']
                                            )
                                        )
                                    );
                                    ?>

                                </p>


                                <p class="mb-2">

                                    <strong>Delivery Time:</strong>

                                    <?php
                                    echo e(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $view_order['delivery_time']
                                            )
                                        )
                                    );
                                    ?>

                                </p>


                                <p class="mb-0">

                                    <strong>Address ID:</strong>

                                    <?php
                                    echo (int)
                                        $view_order['address_id'];
                                    ?>

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="card h-100">

                            <div class="card-body">

                                <h6 class="fw-bold mb-3">
                                    Amount
                                </h6>

                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Subtotal
                                    </span>

                                    <span>
                                        ₹<?php
                                        echo number_format(
                                            (float)
                                            $view_order['subtotal'],
                                            2
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Delivery Fee
                                    </span>

                                    <span>
                                        ₹<?php
                                        echo number_format(
                                            (float)
                                            $view_order['delivery_fee'],
                                            2
                                        );
                                        ?>
                                    </span>

                                </div>


                                <hr>


                                <div class="d-flex justify-content-between">

                                    <strong>
                                        Total
                                    </strong>

                                    <strong class="fs-5">

                                        ₹<?php
                                        echo number_format(
                                            (float)
                                            $view_order['total_amount'],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Items -->

                <h6 class="fw-bold mb-3">
                    Order Items
                </h6>

                <?php if (!empty($view_items)): ?>

                    <div class="table-responsive mb-4">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Meal
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Unit Price
                                    </th>

                                    <th>
                                        Subtotal
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($view_items as $item): ?>

                                    <tr>

                                        <td>
                                            <?php echo e($item['meal_name']); ?>
                                        </td>

                                        <td>
                                            <?php echo (int) $item['quantity']; ?>
                                        </td>

                                        <td>
                                            ₹<?php
                                            echo number_format(
                                                (float)
                                                $item['unit_price'],
                                                2
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            ₹<?php
                                            echo number_format(
                                                (float)
                                                $item['subtotal'],
                                                2
                                            );
                                            ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="alert alert-light border mb-4">
                        No order items found.
                    </div>

                <?php endif; ?>


                <!-- Payments -->

                <h6 class="fw-bold mb-3">
                    Payment Information
                </h6>

                <?php if (!empty($view_payments)): ?>

                    <div class="table-responsive mb-4">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Method
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($view_payments as $payment): ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo e(
                                                $payment['payment_reference']
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            ₹<?php
                                            echo number_format(
                                                (float)
                                                $payment['amount'],
                                                2
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                strtoupper(
                                                    $payment['payment_method']
                                                )
                                            );
                                            ?>
                                        </td>

                                        <td>

                                            <span class="badge bg-secondary">

                                                <?php
                                                echo e(
                                                    ucfirst(
                                                        $payment['payment_status']
                                                    )
                                                );
                                                ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?php
                                            echo e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $payment['created_at']
                                                    )
                                                )
                                            );
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="alert alert-light border mb-4">
                        No payment records found.
                    </div>

                <?php endif; ?>


                <!-- Delivery -->

                <h6 class="fw-bold mb-3">
                    Delivery Assignment
                </h6>

                <?php if (!empty($view_assignments)): ?>

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Dabbawala
                                    </th>

                                    <th>
                                        Phone
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Assigned
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($view_assignments as $assignment): ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo e(
                                                $assignment['dabbawala_name']
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo e(
                                                $assignment['dabbawala_phone']
                                            );
                                            ?>
                                        </td>

                                        <td>

                                            <span class="badge bg-secondary">

                                                <?php
                                                echo e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $assignment['assignment_status']
                                                        )
                                                    )
                                                );
                                                ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?php
                                            echo e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        $assignment['assigned_at']
                                                    )
                                                )
                                            );
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="alert alert-light border">
                        No delivery assignment found.
                    </div>

                <?php endif; ?>


                <?php if (!empty($view_order['notes'])): ?>

                    <div class="mt-4">

                        <h6 class="fw-bold">
                            Notes
                        </h6>

                        <div class="alert alert-light border">

                            <?php
                            echo nl2br(
                                e($view_order['notes'])
                            );
                            ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {

    // ==============================================
    // STATUS MODAL
    // ==============================================

    const statusModal =
        document.getElementById('statusModal');

    if (statusModal) {

        statusModal.addEventListener(
            'show.bs.modal',
            function (event) {

                const button = event.relatedTarget;

                const id =
                    button.getAttribute('data-id');

                const order =
                    button.getAttribute('data-order');

                const status =
                    button.getAttribute('data-status');


                document.getElementById(
                    'status_order_id'
                ).value = id;


                document.getElementById(
                    'status_order_number'
                ).value = order;


                document.getElementById(
                    'status_order_status'
                ).value = status;

            }
        );
    }


    // ==============================================
    // OPEN VIEW MODAL
    // ==============================================

    const viewModal =
        document.getElementById('viewOrderModal');

    if (viewModal) {

        const modal =
            new bootstrap.Modal(viewModal);

        modal.show();

    }

});
</script>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>