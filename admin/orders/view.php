<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'View Order';
$active_page = 'orders';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$order_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$order = null;
$items = [];
$payments = [];
$assignments = [];
$error_message = '';

if (!$order_id || $order_id < 1) {

    $error_message = 'Invalid order ID.';

} else {

    $stmt = $conn->prepare("
        SELECT
            o.*,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone
        FROM orders o
        INNER JOIN users u
            ON o.user_id = u.id
        WHERE o.id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param('i', $order_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        $stmt->close();

    } else {

        $error_message = 'Unable to load order.';
    }
}


if ($order) {

    $stmt = $conn->prepare("
        SELECT
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            m.name AS meal_name
        FROM order_items oi
        INNER JOIN meals m
            ON oi.meal_id = m.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param('i', $order_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $stmt->close();
    }


    $stmt = $conn->prepare("
        SELECT
            payment_reference,
            amount,
            payment_method,
            payment_status,
            transaction_reference,
            paid_at,
            created_at
        FROM payments
        WHERE order_id = ?
        ORDER BY id DESC
    ");

    if ($stmt) {

        $stmt->bind_param('i', $order_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }

        $stmt->close();
    }


    $stmt = $conn->prepare("
        SELECT
            da.*,
            d.name AS dabbawala_name,
            d.phone AS dabbawala_phone
        FROM delivery_assignments da
        INNER JOIN dabbawalas d
            ON da.dabbawala_id = d.id
        WHERE da.order_id = ?
        ORDER BY da.id DESC
    ");

    if ($stmt) {

        $stmt->bind_param('i', $order_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }

        $stmt->close();
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex justify-content-between align-items-center">

        <div>
            <h2>Order Details</h2>

            <p class="text-muted mb-0">
                Complete order information.
            </p>
        </div>

        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Back
        </a>

    </div>

</div>


<?php if ($error_message): ?>

    <div class="alert alert-danger">
        <?php echo e($error_message); ?>
    </div>

<?php elseif (!$order): ?>

    <div class="admin-empty-state">

        <div class="admin-empty-state-icon">
            <i class="bi bi-cart-x"></i>
        </div>

        <h5>Order not found</h5>

    </div>

<?php else: ?>

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="admin-data-card mb-4">

                <div class="admin-data-card-header">
                    <h5 class="mb-0">
                        Order <?php echo e($order['order_number']); ?>
                    </h5>
                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <strong>Customer</strong>
                            <div><?php echo e($order['customer_name']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <strong>Email</strong>
                            <div><?php echo e($order['customer_email']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <strong>Phone</strong>
                            <div><?php echo e($order['customer_phone']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <strong>Status</strong>
                            <div>
                                <span class="badge bg-primary">
                                    <?php echo e(ucfirst($order['order_status'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <strong>Delivery Date</strong>
                            <div><?php echo e($order['delivery_date']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <strong>Delivery Time</strong>
                            <div><?php echo e($order['delivery_time']); ?></div>
                        </div>

                    </div>

                </div>

            </div>


            <div class="admin-data-card mb-4">

                <div class="admin-data-card-header">
                    <h5 class="mb-0">Order Items</h5>
                </div>

                <div class="card-body p-0">

                    <?php if ($items): ?>

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="table-light">

                                    <tr>
                                        <th>Meal</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($items as $item): ?>

                                    <tr>

                                        <td>
                                            <?php echo e($item['meal_name']); ?>
                                        </td>

                                        <td>
                                            <?php echo (int)$item['quantity']; ?>
                                        </td>

                                        <td>
                                            ₹<?php echo number_format((float)$item['unit_price'], 2); ?>
                                        </td>

                                        <td>
                                            ₹<?php echo number_format((float)$item['subtotal'], 2); ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="p-4 text-muted">
                            No order items found.
                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <div class="admin-data-card">

                <div class="admin-data-card-header">
                    <h5 class="mb-0">Delivery Assignments</h5>
                </div>

                <div class="card-body">

                    <?php if ($assignments): ?>

                        <?php foreach ($assignments as $assignment): ?>

                            <div class="border rounded p-3 mb-2">

                                <strong>
                                    <?php echo e($assignment['dabbawala_name']); ?>
                                </strong>

                                <div class="small text-muted">
                                    <?php echo e($assignment['dabbawala_phone']); ?>
                                </div>

                                <span class="badge bg-secondary mt-2">
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

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No delivery assignment.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div class="col-lg-4">

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <h6 class="text-muted">
                        Subtotal
                    </h6>

                    <h5>
                        ₹<?php echo number_format((float)$order['subtotal'], 2); ?>
                    </h5>

                    <h6 class="text-muted mt-3">
                        Delivery Fee
                    </h6>

                    <h5>
                        ₹<?php echo number_format((float)$order['delivery_fee'], 2); ?>
                    </h5>

                    <hr>

                    <h6 class="text-muted">
                        Total
                    </h6>

                    <h3 class="fw-bold">
                        ₹<?php echo number_format((float)$order['total_amount'], 2); ?>
                    </h3>

                </div>

            </div>


            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h5 class="mb-3">
                        Payments
                    </h5>

                    <?php if ($payments): ?>

                        <?php foreach ($payments as $payment): ?>

                            <div class="border-bottom pb-3 mb-3">

                                <strong>
                                    ₹<?php echo number_format((float)$payment['amount'], 2); ?>
                                </strong>

                                <div class="small text-muted">
                                    <?php echo e($payment['payment_method']); ?>
                                </div>

                                <span class="badge bg-secondary mt-1">
                                    <?php echo e($payment['payment_status']); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            No payment records.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>