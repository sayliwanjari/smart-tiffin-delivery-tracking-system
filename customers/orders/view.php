<?php

require_once __DIR__ . '/../includes/customer/customer_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Order Details';
$active_page = 'orders';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customer_id = (int) $_SESSION['customer_id'];

$order_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$order = null;
$items = [];
$error_message = '';

if (!$order_id || $order_id <= 0) {

    $error_message = 'Invalid order selected.';

} else {

    $stmt = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.delivery_date,
            o.delivery_time,
            o.subtotal,
            o.delivery_fee,
            o.total_amount,
            o.order_status,
            o.notes,
            o.created_at,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            a.address_label,
            a.address_line,
            a.landmark,
            a.city,
            a.state,
            a.postal_code

        FROM orders o

        INNER JOIN users u
            ON u.id = o.user_id

        LEFT JOIN addresses a
            ON a.id = o.address_id

        WHERE o.id = ?
          AND o.user_id = ?

        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            'ii',
            $order_id,
            $customer_id
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        $stmt->close();

    } else {
        $error_message = 'Unable to load order details.';
    }

    if (!$order && $error_message === '') {
        $error_message = 'Order not found.';
    }
}

if ($order) {

    $stmt = $conn->prepare("
        SELECT
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            m.name AS meal_name,
            m.description,
            m.image

        FROM order_items oi

        INNER JOIN meals m
            ON m.id = oi.meal_id

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
}

require_once __DIR__ . '/../includes/customer/customer_header.php';
?>

<div class="container py-4 py-lg-5">

    <?php if ($error_message): ?>

        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <a href="index.php"
           class="btn btn-warning">

            <i class="bi bi-arrow-left me-1"></i>
            Back to My Orders

        </a>

    <?php else: ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

            <div>

                <a href="index.php"
                   class="text-decoration-none text-dark small">

                    <i class="bi bi-arrow-left me-1"></i>
                    My Orders

                </a>

                <h1 class="fw-bold mt-2 mb-0">
                    Order Details
                </h1>

            </div>

            <a href="../tracking.php?order_id=<?= (int)$order['id'] ?>"
               class="btn btn-warning rounded-3 fw-semibold">

                <i class="bi bi-geo-alt me-1"></i>
                Track Delivery

            </a>

        </div>

        <div class="row g-4">

            <div class="col-lg-8">

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                            <div>

                                <small class="text-muted">
                                    Order Number
                                </small>

                                <h4 class="fw-bold mb-0">
                                    <?= htmlspecialchars(
                                        $order['order_number'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h4>

                            </div>

                            <span class="badge rounded-pill text-bg-<?=
                                match (strtolower($order['order_status'])) {
                                    'pending' => 'warning',
                                    'confirmed' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                }
                            ?>">

                                <?= htmlspecialchars(
                                    ucfirst($order['order_status']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>

                    </div>

                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-egg-fried text-warning me-2"></i>
                            Your Meal
                        </h5>

                        <?php if (empty($items)): ?>

                            <p class="text-muted mb-0">
                                No meal items found for this order.
                            </p>

                        <?php else: ?>

                            <?php foreach ($items as $item): ?>

                                <div class="d-flex gap-3 mb-4">

                                    <?php if (!empty($item['image'])): ?>

                                        <img
                                            src="<?= htmlspecialchars(
                                                $item['image'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $item['meal_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            style="width:80px;height:80px;object-fit:cover;"
                                            class="rounded-3"
                                        >

                                    <?php else: ?>

                                        <div class="rounded-3 bg-warning-subtle
                                                    d-flex align-items-center justify-content-center"
                                             style="width:80px;height:80px;">

                                            <i class="bi bi-egg-fried fs-3 text-warning"></i>

                                        </div>

                                    <?php endif; ?>

                                    <div class="flex-grow-1">

                                        <h6 class="fw-bold mb-1">

                                            <?= htmlspecialchars(
                                                $item['meal_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h6>

                                        <div class="text-muted small">
                                            Quantity:
                                            <?= (int)$item['quantity'] ?>
                                        </div>

                                        <div class="text-muted small">
                                            ₹<?= number_format(
                                                (float)$item['unit_price'],
                                                2
                                            ) ?>
                                            ×
                                            <?= (int)$item['quantity'] ?>
                                        </div>

                                    </div>

                                    <strong>
                                        ₹<?= number_format(
                                            (float)$item['subtotal'],
                                            2
                                        ) ?>
                                    </strong>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="card border-0 shadow-sm rounded-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-geo-alt text-warning me-2"></i>
                            Delivery Address
                        </h5>

                        <?php if (!empty($order['address_line'])): ?>

                            <div class="text-muted">

                                <strong class="text-dark">
                                    <?= htmlspecialchars(
                                        $order['address_label'] ?: 'Delivery Address',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <br>

                                <?= htmlspecialchars(
                                    $order['address_line'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                <?php if (!empty($order['landmark'])): ?>
                                    <br>
                                    Landmark:
                                    <?= htmlspecialchars(
                                        $order['landmark'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                <?php endif; ?>

                                <br>

                                <?= htmlspecialchars(
                                    $order['city'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>,

                                <?= htmlspecialchars(
                                    $order['state'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $order['postal_code'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                        <?php else: ?>

                            <p class="text-muted mb-0">
                                Delivery address is unavailable.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <div class="col-lg-4">

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-4">
                            Order Summary
                        </h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span>
                                ₹<?= number_format(
                                    (float)$order['subtotal'],
                                    2
                                ) ?>
                            </span>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Delivery Fee</span>
                            <span>
                                ₹<?= number_format(
                                    (float)$order['delivery_fee'],
                                    2
                                ) ?>
                            </span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between fs-5 fw-bold">
                            <span>Total</span>

                            <span class="text-warning">
                                ₹<?= number_format(
                                    (float)$order['total_amount'],
                                    2
                                ) ?>
                            </span>
                        </div>

                    </div>

                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-4">
                            Delivery
                        </h5>

                        <div class="mb-3">

                            <small class="text-muted">
                                Delivery Date
                            </small>

                            <div class="fw-semibold">
                                <?= htmlspecialchars(
                                    $order['delivery_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        </div>

                        <div>

                            <small class="text-muted">
                                Delivery Time
                            </small>

                            <div class="fw-semibold">
                                <?= htmlspecialchars(
                                    $order['delivery_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="card border-0 bg-light rounded-4">

                    <div class="card-body p-4">

                        <h6 class="fw-bold">
                            Payment
                        </h6>

                        <p class="text-muted mb-0">
                            Demo / Not implemented
                        </p>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/customer/customer_footer.php';
?>