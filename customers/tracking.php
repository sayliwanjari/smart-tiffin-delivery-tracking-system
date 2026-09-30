<?php



require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/customer_auth.php';
$page_title = 'Track Delivery';
$active_page = 'tracking';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customer_id = (int) $_SESSION['customer_id'];

$order_id = filter_input(
    INPUT_GET,
    'order_id',
    FILTER_VALIDATE_INT
);

$order = null;
$tracking = [];
$gps = null;
$error_message = '';

/*
|--------------------------------------------------------------------------
| Customer-owned order + assignment
|--------------------------------------------------------------------------
*/

if (!$order_id || $order_id <= 0) {

    $error_message = 'Please select a valid order to track.';

} else {

    $stmt = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.delivery_date,
            o.delivery_time,
            o.order_status,

            da.id AS assignment_id,
            da.dabbawala_id,
            da.assignment_status

        FROM orders o

        LEFT JOIN delivery_assignments da
            ON da.order_id = o.id

        WHERE o.id = ?
          AND o.user_id = ?

        ORDER BY da.id DESC

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

        $error_message = 'Unable to load tracking information.';
    }

    if (!$order && $error_message === '') {
        $error_message = 'Order not found.';
    }
}

/*
|--------------------------------------------------------------------------
| Tracking history
|--------------------------------------------------------------------------
*/

if ($order && !empty($order['assignment_id'])) {

    $assignment_id = (int)$order['assignment_id'];

    $stmt = $conn->prepare("
        SELECT
            id,
            status,
            remarks,
            updated_at
        FROM delivery_tracking
        WHERE assignment_id = ?
        ORDER BY updated_at DESC, id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $assignment_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $tracking[] = $row;
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Latest GPS location
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            latitude,
            longitude,
            recorded_at
        FROM gps_locations
        WHERE assignment_id = ?
        ORDER BY recorded_at DESC, id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $assignment_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $gps = $result->fetch_assoc();

        $stmt->close();
    }
}

$current_status = 'assigned';

if (!empty($tracking)) {
    $current_status = strtolower(
        (string)$tracking[0]['status']
    );
}

$steps = [
    'assigned' => 'Order Assigned',
    'accepted' => 'Dabbawala Accepted',
    'picked_up' => 'Tiffin Picked Up',
    'out_for_delivery' => 'Out for Delivery',
    'near_customer' => 'Near You',
    'delivered' => 'Delivered'
];

require_once __DIR__ . '/includes/customer_header.php';

?>

<div class="container py-4 py-lg-5">

    <div class="mb-4">

        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2">
            <i class="bi bi-geo-alt me-1"></i>
            Live Delivery
        </span>

        <h1 class="fw-bold mt-3 mb-1">
            Track Your Tiffin
        </h1>

        <p class="text-muted mb-0">
            Follow your delivery from kitchen to doorstep.
        </p>

    </div>

    <?php if ($error_message): ?>

        <div class="alert alert-warning border-0 shadow-sm">

            <i class="bi bi-exclamation-circle me-2"></i>

            <?= htmlspecialchars(
                $error_message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

        <a href="orders/index.php"
           class="btn btn-warning">

            <i class="bi bi-bag me-1"></i>
            My Orders

        </a>

    <?php elseif ($order): ?>

        <div class="row g-4">

            <div class="col-lg-5">

                <div class="card border-0 shadow-sm rounded-4">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-center mb-4">

                            <div>

                                <small class="text-muted">
                                    Order
                                </small>

                                <h5 class="fw-bold mb-0">
                                    <?= htmlspecialchars(
                                        $order['order_number'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h5>

                            </div>

                            <i class="bi bi-truck fs-2 text-warning"></i>

                        </div>

                        <?php if (!empty($order['assignment_id'])): ?>

                            <div class="alert alert-success border-0">

                                <i class="bi bi-check-circle me-2"></i>

                                Delivery assigned

                            </div>

                            <div class="small text-muted mb-1">
                                Dabbawala ID
                            </div>

                            <div class="fw-semibold mb-3">
                                #<?= (int)$order['dabbawala_id'] ?>
                            </div>

                            <div class="small text-muted mb-1">
                                Delivery Status
                            </div>

                            <div class="fw-semibold text-capitalize">
                                <?= htmlspecialchars(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $current_status
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        <?php else: ?>

                            <div class="text-center py-4">

                                <i class="bi bi-hourglass-split display-4 text-warning"></i>

                                <h5 class="fw-bold mt-3">
                                    Waiting for Assignment
                                </h5>

                                <p class="text-muted mb-0">
                                    Your dabbawala will appear here once
                                    the delivery is assigned.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <?php if (!empty($tracking)): ?>

                    <div class="card border-0 shadow-sm rounded-4 mt-4">

                        <div class="card-body p-4">

                            <h5 class="fw-bold mb-4">
                                Delivery Progress
                            </h5>

                            <div class="position-relative">

                                <?php foreach ($steps as $status => $label): ?>

                                    <?php

                                    $current_index = array_search(
                                        $current_status,
                                        array_keys($steps),
                                        true
                                    );

                                    $step_index = array_search(
                                        $status,
                                        array_keys($steps),
                                        true
                                    );

                                    $completed =
                                        $current_index !== false &&
                                        $step_index <= $current_index;

                                    ?>

                                    <div class="d-flex gap-3 mb-4">

                                        <div class="rounded-circle
                                            <?= $completed
                                                ? 'bg-warning text-white'
                                                : 'bg-light text-muted' ?>
                                            d-flex align-items-center
                                            justify-content-center"
                                            style="width:38px;height:38px;min-width:38px;">

                                            <i class="bi bi-check-lg"></i>

                                        </div>

                                        <div>

                                            <div class="fw-semibold">
                                                <?= htmlspecialchars(
                                                    $label,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </div>

                                            <?php if ($status === $current_status): ?>

                                                <small class="text-warning">
                                                    Current status
                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

            <div class="col-lg-7">

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">

                    <div class="card-body p-0">

                        <?php if ($gps): ?>

                            <div id="trackingMap"
                                 style="height:450px;width:100%;"></div>

                            <div class="p-3 bg-light">

                                <div class="fw-semibold">

                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>

                                    Dabbawala Location

                                </div>

                                <small class="text-muted">

                                    Last updated:

                                    <?= htmlspecialchars(
                                        $gps['recorded_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            </div>

                        <?php else: ?>

                            <div class="text-center py-5 px-4">

                                <div class="rounded-circle bg-warning-subtle
                                            d-inline-flex align-items-center
                                            justify-content-center mb-3"
                                     style="width:80px;height:80px;">

                                    <i class="bi bi-geo-alt fs-1 text-warning"></i>

                                </div>

                                <h4 class="fw-bold">
                                    Live location is not available yet
                                </h4>

                                <p class="text-muted mb-0">
                                    Your dabbawala's location will appear
                                    here once delivery tracking starts.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php if ($gps): ?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const latitude = <?= json_encode((float)$gps['latitude']) ?>;
    const longitude = <?= json_encode((float)$gps['longitude']) ?>;

    const map = L.map('trackingMap').setView(
        [latitude, longitude],
        15
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);

    const marker = L.marker([
        latitude,
        longitude
    ]).addTo(map);

    marker.bindPopup(
        '<strong>Dabbawala Location</strong><br>' +
        'Last updated: <?= htmlspecialchars(
            $gps['recorded_at'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>'
    ).openPopup();

});

</script>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/customer_footer.php';
?>