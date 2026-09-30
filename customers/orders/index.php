<?php

require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'My Orders';
$active_page = 'orders';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customer_id = (int) $_SESSION['customer_id'];

$success_message = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']);

$orders = [];
$error_message = '';

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
        o.created_at,

        a.address_line,
        a.landmark,
        a.city,
        a.state,
        a.postal_code,

        GROUP_CONCAT(
            CONCAT(m.name, ' × ', oi.quantity)
            ORDER BY oi.id
            SEPARATOR ', '
        ) AS meals

    FROM orders o

    LEFT JOIN addresses a
        ON a.id = o.address_id

    LEFT JOIN order_items oi
        ON oi.order_id = o.id

    LEFT JOIN meals m
        ON m.id = oi.meal_id

    WHERE o.user_id = ?

    GROUP BY
        o.id,
        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.subtotal,
        o.delivery_fee,
        o.total_amount,
        o.order_status,
        o.created_at,
        a.address_line,
        a.landmark,
        a.city,
        a.state,
        a.postal_code

    ORDER BY o.created_at DESC
");

if ($stmt) {

    $stmt->bind_param('i', $customer_id);

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

    } else {
        $error_message = 'Unable to load your orders.';
    }

    $stmt->close();

} else {
    $error_message = 'Unable to load your orders.';
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="container py-4 py-lg-5">

    <div class="mb-4">
        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2">
            <i class="bi bi-bag-check me-1"></i>
            My Orders
        </span>

        <h1 class="fw-bold mt-3 mb-1">
            Your Tiffin Orders
        </h1>

        <p class="text-muted mb-0">
            Keep track of your meals and deliveries in one place.
        </p>
    </div>

    <?php if ($success_message): ?>

        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <?php if ($error_message): ?>

        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php elseif (empty($orders)): ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">

                <div class="rounded-circle bg-warning-subtle d-inline-flex
                            align-items-center justify-content-center mb-3"
                     style="width:80px;height:80px;">

                    <i class="bi bi-bag-x fs-1 text-warning"></i>

                </div>

                <h3 class="fw-bold">
                    No orders yet
                </h3>

                <p class="text-muted">
                    You don't have any orders yet.
                    Explore our meals and get started.
                </p>

                <a href="../meals/index.php"
                   class="btn btn-warning rounded-3 px-4 fw-semibold">

                    <i class="bi bi-egg-fried me-2"></i>
                    Explore Meals

                </a>

            </div>
        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($orders as $order): ?>

                <?php

                $status = strtolower((string)$order['order_status']);

                $status_class = match ($status) {
                    'pending' => 'warning',
                    'confirmed' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    default => 'secondary'
                };

                ?>

                <div class="col-12">

                    <div class="card border-0 shadow-sm rounded-4">

                        <div class="card-body p-4">

                            <div class="row align-items-center g-4">

                                <div class="col-lg-7">

                                    <div class="d-flex align-items-start gap-3">

                                        <div class="rounded-3 bg-warning-subtle
                                                    d-flex align-items-center justify-content-center"
                                             style="width:52px;height:52px;">

                                            <i class="bi bi-bag-heart fs-4 text-warning"></i>

                                        </div>

                                        <div>

                                            <div class="text-muted small">
                                                Order
                                            </div>

                                            <h5 class="fw-bold mb-1">
                                                <?= htmlspecialchars(
                                                    $order['order_number'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h5>

                                            <span class="badge text-bg-<?= $status_class ?> rounded-pill">
                                                <?= htmlspecialchars(
                                                    ucfirst($status),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>

                                    <div class="mt-4">

                                        <div class="fw-semibold">
                                            <i class="bi bi-egg-fried text-warning me-2"></i>

                                            <?= htmlspecialchars(
                                                $order['meals'] ?: 'Tiffin Meal',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </div>

                                        <div class="text-muted small mt-2">

                                            <i class="bi bi-calendar3 me-1"></i>
                                            Delivery:
                                            <?= htmlspecialchars(
                                                $order['delivery_date'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            &nbsp;

                                            <i class="bi bi-clock me-1"></i>
                                            <?= htmlspecialchars(
                                                $order['delivery_time'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </div>

                                        <div class="text-muted small mt-2">

                                            <i class="bi bi-geo-alt me-1"></i>

                                            <?= htmlspecialchars(
                                                $order['city'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            <?= !empty($order['state'])
                                                ? ', ' . htmlspecialchars(
                                                    $order['state'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                                : '' ?>

                                        </div>

                                    </div>

                                </div>

                                <div class="col-lg-5 text-lg-end">

                                    <div class="text-muted small">
                                        Total Amount
                                    </div>

                                    <div class="fs-3 fw-bold text-warning mb-3">

                                        ₹<?= number_format(
                                            (float)$order['total_amount'],
                                            2
                                        ) ?>

                                    </div>

                                    <div class="d-flex gap-2 justify-content-lg-end flex-wrap">

                                        <a href="view.php?id=<?= (int)$order['id'] ?>"
                                           class="btn btn-outline-dark rounded-3">

                                            <i class="bi bi-eye me-1"></i>
                                            View Details

                                        </a>

                                        <a href="../tracking.php?order_id=<?= (int)$order['id'] ?>"
                                           class="btn btn-warning rounded-3 fw-semibold">

                                            <i class="bi bi-geo-alt me-1"></i>
                                            Track Delivery

                                        </a>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/customer_footer.php';
?>