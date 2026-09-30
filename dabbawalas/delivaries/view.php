<?php

require_once __DIR__ . '/../includes/dabbawala_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Delivery Details';
$active_page = 'deliveries';

function dabbawala_view_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$assignment_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$assignment_id || $assignment_id <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get delivery
|--------------------------------------------------------------------------
| IMPORTANT:
| The dabbawala_id condition prevents one dabbawala from viewing
| another dabbawala's assignment.
*/

$delivery = null;

$sql = "
    SELECT
        da.id AS assignment_id,
        da.order_id,
        da.dabbawala_id,
        da.assigned_at,
        da.accepted_at,
        da.rejected_at,
        da.rejection_reason,
        da.completed_at,
        da.assignment_status,

        o.order_number,
        o.user_id,
        o.delivery_date,
        o.delivery_time,
        o.subtotal,
        o.delivery_fee,
        o.total_amount,
        o.order_status,
        o.notes,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,

        a.address_label,
        a.address_line,
        a.landmark,
        a.city,
        a.state,
        a.postal_code,
        a.latitude AS customer_latitude,
        a.longitude AS customer_longitude

    FROM delivery_assignments da

    INNER JOIN orders o
        ON o.id = da.order_id

    INNER JOIN users u
        ON u.id = o.user_id

    LEFT JOIN addresses a
        ON a.id = o.address_id

    WHERE da.id = ?
      AND da.dabbawala_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        'ii',
        $assignment_id,
        $dabbawala_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $delivery = $result->fetch_assoc();
        }
    }

    $stmt->close();
}

if (!$delivery) {
    header('Location: index.php?error=delivery_not_found');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get order items
|--------------------------------------------------------------------------
*/

$order_items = [];

$item_sql = "
    SELECT
        oi.id,
        oi.meal_id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        m.name AS meal_name

    FROM order_items oi

    INNER JOIN meals m
        ON m.id = oi.meal_id

    WHERE oi.order_id = ?

    ORDER BY oi.id ASC
";

$item_stmt = $conn->prepare($item_sql);

if ($item_stmt) {

    $item_stmt->bind_param(
        'i',
        $delivery['order_id']
    );

    if ($item_stmt->execute()) {

        $item_result = $item_stmt->get_result();

        while ($item_row = $item_result->fetch_assoc()) {
            $order_items[] = $item_row;
        }
    }

    $item_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get delivery tracking history
|--------------------------------------------------------------------------
*/

$tracking_history = [];

$tracking_sql = "
    SELECT
        id,
        status,
        remarks,
        updated_at

    FROM delivery_tracking

    WHERE assignment_id = ?

    ORDER BY updated_at ASC, id ASC
";

$tracking_stmt = $conn->prepare($tracking_sql);

if ($tracking_stmt) {

    $tracking_stmt->bind_param(
        'i',
        $assignment_id
    );

    if ($tracking_stmt->execute()) {

        $tracking_result = $tracking_stmt->get_result();

        while ($tracking_row = $tracking_result->fetch_assoc()) {
            $tracking_history[] = $tracking_row;
        }
    }

    $tracking_stmt->close();
}

$current_status = 'assigned';

if (
    strtolower((string) $delivery['assignment_status']) === 'rejected'
) {
    $current_status = 'rejected';
} elseif (!empty($tracking_history)) {

    $last_tracking = end($tracking_history);

    if (!empty($last_tracking['status'])) {
        $current_status = strtolower(
            $last_tracking['status']
        );
    }

} elseif (!empty($delivery['assignment_status'])) {

    $current_status = strtolower(
        $delivery['assignment_status']
    );
}
/*
|--------------------------------------------------------------------------
| Timeline
|--------------------------------------------------------------------------
*/

$timeline = [
    'assigned' => [
        'label' => 'Assigned',
        'icon' => 'bi-clipboard-check'
    ],
    'accepted' => [
        'label' => 'Accepted',
        'icon' => 'bi-hand-thumbs-up'
    ],
    'picked_up' => [
        'label' => 'Picked Up',
        'icon' => 'bi-box-seam'
    ],
    'out_for_delivery' => [
        'label' => 'Out for Delivery',
        'icon' => 'bi-bicycle'
    ],
    'near_customer' => [
        'label' => 'Near Customer',
        'icon' => 'bi-geo-alt'
    ],
    'delivered' => [
        'label' => 'Delivered',
        'icon' => 'bi-check-circle'
    ]
];

$timeline_keys = array_keys($timeline);

$current_index = array_search(
    $current_status,
    $timeline_keys,
    true
);

if ($current_index === false) {
    $current_index = 0;
}

/*
|--------------------------------------------------------------------------
| Address
|--------------------------------------------------------------------------
*/

$full_address_parts = [];

if (!empty($delivery['address_line'])) {
    $full_address_parts[] = $delivery['address_line'];
}

if (!empty($delivery['landmark'])) {
    $full_address_parts[] = $delivery['landmark'];
}

if (!empty($delivery['city'])) {
    $full_address_parts[] = $delivery['city'];
}

if (!empty($delivery['state'])) {
    $full_address_parts[] = $delivery['state'];
}

if (!empty($delivery['postal_code'])) {
    $full_address_parts[] = $delivery['postal_code'];
}

$full_address = implode(', ', $full_address_parts);

if ($full_address === '') {
    $full_address = 'Delivery address not available';
}

require_once __DIR__ . '/../includes/dabbawala_header.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>

            <a
                href="index.php"
                class="text-decoration-none text-muted small"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Back to My Deliveries
            </a>

            <h2 class="fw-bold mt-2 mb-1">
                Delivery #<?= (int) $delivery['assignment_id'] ?>
            </h2>

            <p class="text-muted mb-0">
                Order #<?= dabbawala_view_e($delivery['order_number']) ?>
            </p>

        </div>

        <div>

            <span class="badge rounded-pill bg-warning text-dark px-4 py-2">
                <i class="bi bi-bicycle me-1"></i>

                <?= dabbawala_view_e(
                    ucwords(str_replace('_', ' ', $current_status))
                ) ?>

            </span>

        </div>

    </div>


    <!-- Customer + Delivery Information -->

    <div class="row g-4 mb-4">

        <!-- Customer -->
        <div class="col-lg-6">

            <div class="card border-0 shadow-sm rounded-4 h-100">

                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">

                        <div
                            class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center"
                            style="width:52px;height:52px;"
                        >
                            <i class="bi bi-person fs-4"></i>
                        </div>

                        <div>
                            <h5 class="fw-bold mb-0">
                                Customer Information
                            </h5>

                            <small class="text-muted">
                                Delivery recipient
                            </small>
                        </div>

                    </div>

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Name
                        </small>

                        <div class="fw-semibold">
                            <?= dabbawala_view_e($delivery['customer_name']) ?>
                        </div>

                    </div>

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Phone
                        </small>

                        <div class="fw-semibold">
                            <i class="bi bi-telephone me-1"></i>
                            <?= dabbawala_view_e($delivery['customer_phone']) ?>
                        </div>

                    </div>

                    <?php if (!empty($delivery['customer_email'])): ?>

                        <div>

                            <small class="text-muted d-block">
                                Email
                            </small>

                            <div class="fw-semibold">
                                <i class="bi bi-envelope me-1"></i>
                                <?= dabbawala_view_e($delivery['customer_email']) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Delivery Address -->
        <div class="col-lg-6">

            <div class="card border-0 shadow-sm rounded-4 h-100">

                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">

                        <div
                            class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
                            style="width:52px;height:52px;"
                        >
                            <i class="bi bi-geo-alt fs-4"></i>
                        </div>

                        <div>
                            <h5 class="fw-bold mb-0">
                                Delivery Address
                            </h5>

                            <small class="text-muted">
                                Destination
                            </small>
                        </div>

                    </div>

                    <?php if (!empty($delivery['address_label'])): ?>

                        <span class="badge bg-light text-dark border rounded-pill mb-2">
                            <?= dabbawala_view_e($delivery['address_label']) ?>
                        </span>

                    <?php endif; ?>

                    <div class="fw-semibold mb-2">
                        <?= dabbawala_view_e($full_address) ?>
                    </div>

                    <?php if (!empty($delivery['customer_latitude']) && !empty($delivery['customer_longitude'])): ?>

                        <div class="small text-muted">

                            <i class="bi bi-pin-map me-1"></i>

                            Location available

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Order Information -->

    <div class="row g-4 mb-4">

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-receipt text-warning me-2"></i>
                        Order Items
                    </h5>

                    <?php if (empty($order_items)): ?>

                        <div class="alert alert-light border rounded-4 mb-0">
                            No order items found.
                        </div>

                    <?php else: ?>

                        <?php foreach ($order_items as $item): ?>

                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">

                                <div>

                                    <div class="fw-semibold">
                                        <?= dabbawala_view_e($item['meal_name']) ?>
                                    </div>

                                    <small class="text-muted">
                                        Quantity:
                                        <?= (int) $item['quantity'] ?>
                                    </small>

                                </div>

                                <div class="text-end">

                                    <div class="fw-semibold">
                                        ₹<?= number_format(
                                            (float) $item['subtotal'],
                                            2
                                        ) ?>
                                    </div>

                                    <small class="text-muted">
                                        ₹<?= number_format(
                                            (float) $item['unit_price'],
                                            2
                                        ) ?>
                                        ×
                                        <?= (int) $item['quantity'] ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Amount -->
        <div class="col-lg-4">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-4">
                        Order Summary
                    </h5>

                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Subtotal
                        </span>

                        <strong>
                            ₹<?= number_format(
                                (float) $delivery['subtotal'],
                                2
                            ) ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between mb-3">

                        <span class="text-muted">
                            Delivery Fee
                        </span>

                        <strong>
                            ₹<?= number_format(
                                (float) $delivery['delivery_fee'],
                                2
                            ) ?>
                        </strong>

                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">

                        <span class="fw-bold">
                            Total
                        </span>

                        <span class="fw-bold fs-4 text-warning">
                            ₹<?= number_format(
                                (float) $delivery['total_amount'],
                                2
                            ) ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Delivery Schedule -->

    <div class="card border-0 shadow-sm rounded-4 mb-4">

        <div class="card-body p-4">

            <h5 class="fw-bold mb-4">
                <i class="bi bi-calendar-check text-warning me-2"></i>
                Delivery Schedule
            </h5>

            <div class="row g-4">

                <div class="col-md-4">

                    <small class="text-muted d-block">
                        Delivery Date
                    </small>

                    <strong>
                        <?= dabbawala_view_e($delivery['delivery_date']) ?>
                    </strong>

                </div>

                <div class="col-md-4">

                    <small class="text-muted d-block">
                        Delivery Time
                    </small>

                    <strong>
                        <?= dabbawala_view_e($delivery['delivery_time']) ?>
                    </strong>

                </div>

                <div class="col-md-4">

                    <small class="text-muted d-block">
                        Order Status
                    </small>

                    <span class="badge bg-primary rounded-pill">
                        <?= dabbawala_view_e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $delivery['order_status']
                                )
                            )
                        ) ?>
                    </span>

                </div>

            </div>

            <?php if (!empty($delivery['notes'])): ?>

                <div class="alert alert-light border rounded-4 mt-4 mb-0">

                    <strong>
                        <i class="bi bi-chat-left-text me-1"></i>
                        Customer Notes
                    </strong>

                    <div class="mt-2 text-muted">
                        <?= nl2br(
                            dabbawala_view_e($delivery['notes'])
                        ) ?>
                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>
    <!-- Delivery Timeline -->
     <!-- Delivery Actions -->

<div class="card border-0 shadow-sm rounded-4 mb-4">

    <div class="card-body p-4">

        <div class="d-flex align-items-center gap-3 mb-4">

            <div
                class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center"
                style="width:50px;height:50px;"
            >
                <i class="bi bi-lightning-charge fs-4"></i>
            </div>

            <div>
                <h5 class="fw-bold mb-1">
                    Delivery Actions
                </h5>

                <small class="text-muted">
                    Update the current delivery
                </small>
            </div>

        </div>

        <?php
        $csrf_token = '';

        if (function_exists('generate_csrf_token')) {
            $csrf_token = generate_csrf_token();
        } elseif (isset($_SESSION['csrf_token'])) {
            $csrf_token = $_SESSION['csrf_token'];
        }
        ?>

        <?php if ($current_status === 'assigned'): ?>

            <div class="d-flex flex-wrap gap-2">

                <!-- Accept -->
                <form
                    method="POST"
                    action="accept.php"
                    class="d-inline"
                >

                    <input
                        type="hidden"
                        name="assignment_id"
                        value="<?= (int) $assignment_id ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= dabbawala_view_e($csrf_token) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-success rounded-pill px-4"
                    >
                        <i class="bi bi-check-circle me-1"></i>
                        Accept Delivery
                    </button>

                </form>


                <!-- Reject -->
                <button
                    type="button"
                    class="btn btn-outline-danger rounded-pill px-4"
                    data-bs-toggle="modal"
                    data-bs-target="#rejectDeliveryModal"
                >
                    <i class="bi bi-x-circle me-1"></i>
                    Reject
                </button>

            </div>

        <?php elseif ($current_status === 'accepted'): ?>

            <form
                method="POST"
                action="update_status.php"
                class="row g-3"
            >

                <input
                    type="hidden"
                    name="assignment_id"
                    value="<?= (int) $assignment_id ?>"
                >

                <input
                    type="hidden"
                    name="status"
                    value="picked_up"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= dabbawala_view_e($csrf_token) ?>"
                >

                <div class="col-md-8">

                    <input
                        type="text"
                        name="remarks"
                        class="form-control rounded-3"
                        maxlength="500"
                        placeholder="Optional remark"
                    >

                </div>

                <div class="col-md-4">

                    <button
                        type="submit"
                        class="btn btn-warning w-100 rounded-pill"
                    >
                        <i class="bi bi-box-seam me-1"></i>
                        Mark Picked Up
                    </button>

                </div>

            </form>

        <?php elseif ($current_status === 'picked_up'): ?>

            <form
                method="POST"
                action="update_status.php"
                class="row g-3"
            >

                <input
                    type="hidden"
                    name="assignment_id"
                    value="<?= (int) $assignment_id ?>"
                >

                <input
                    type="hidden"
                    name="status"
                    value="out_for_delivery"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= dabbawala_view_e($csrf_token) ?>"
                >

                <div class="col-md-8">

                    <input
                        type="text"
                        name="remarks"
                        class="form-control rounded-3"
                        maxlength="500"
                        placeholder="Optional remark"
                    >

                </div>

                <div class="col-md-4">

                    <button
                        type="submit"
                        class="btn btn-primary w-100 rounded-pill"
                    >
                        <i class="bi bi-bicycle me-1"></i>
                        Start Delivery
                    </button>

                </div>

            </form>

        <?php elseif ($current_status === 'out_for_delivery'): ?>

            <div class="d-flex flex-wrap gap-2">

                <form
                    method="POST"
                    action="update_status.php"
                >

                    <input
                        type="hidden"
                        name="assignment_id"
                        value="<?= (int) $assignment_id ?>"
                    >

                    <input
                        type="hidden"
                        name="status"
                        value="near_customer"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= dabbawala_view_e($csrf_token) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-warning rounded-pill px-4"
                    >
                        <i class="bi bi-geo-alt me-1"></i>
                        Near Customer
                    </button>

                </form>

            </div>

        <?php elseif ($current_status === 'near_customer'): ?>

            <form
                method="POST"
                action="update_status.php"
            >

                <input
                    type="hidden"
                    name="assignment_id"
                    value="<?= (int) $assignment_id ?>"
                >

                <input
                    type="hidden"
                    name="status"
                    value="delivered"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= dabbawala_view_e($csrf_token) ?>"
                >

                <button
                    type="submit"
                    class="btn btn-success rounded-pill px-4"
                >
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Mark Delivered
                </button>

            </form>

        <?php elseif ($current_status === 'delivered'): ?>

            <div class="alert alert-success rounded-4 border-0 mb-0">

                <i class="bi bi-check-circle-fill me-2"></i>

                This delivery has been successfully completed.

            </div>

        <?php elseif ($current_status === 'rejected'): ?>

            <div class="alert alert-danger rounded-4 border-0 mb-0">

                <i class="bi bi-x-circle-fill me-2"></i>

                This delivery was rejected.

            </div>

        <?php elseif ($current_status === 'failed'): ?>

            <div class="alert alert-danger rounded-4 border-0 mb-0">

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                This delivery has been marked as failed.

            </div>

        <?php elseif ($current_status === 'cancelled'): ?>

            <div class="alert alert-secondary rounded-4 border-0 mb-0">

                <i class="bi bi-slash-circle me-2"></i>

                This delivery has been cancelled.

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- Reject Modal -->

<div
    class="modal fade"
    id="rejectDeliveryModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 rounded-4 shadow">

            <form
                method="POST"
                action="reject.php"
            >

                <div class="modal-header border-0">

                    <h5 class="modal-title fw-bold">
                        Reject Delivery
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="assignment_id"
                        value="<?= (int) $assignment_id ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= dabbawala_view_e($csrf_token) ?>"
                    >

                    <label class="form-label fw-semibold">
                        Reason for rejection
                    </label>

                    <textarea
                        name="rejection_reason"
                        class="form-control rounded-3"
                        rows="4"
                        maxlength="500"
                        required
                        placeholder="Please explain why you cannot accept this delivery..."
                    ></textarea>

                </div>

                <div class="modal-footer border-0">

                    <button
                        type="button"
                        class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger rounded-pill px-4"
                    >
                        <i class="bi bi-x-circle me-1"></i>
                        Reject Delivery
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>






    <!-- Delivery Timeline -->

    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>
                    <h5 class="fw-bold mb-1">
                        Delivery Progress
                    </h5>

                    <p class="text-muted small mb-0">
                        Current delivery status
                    </p>
                </div>

                <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                    <?= dabbawala_view_e(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $current_status
                            )
                        )
                    ) ?>
                </span>

            </div>

            <?php if (
                $current_status === 'failed' ||
                $current_status === 'cancelled' ||
                $current_status === 'rejected'
            ): ?>

                <div class="alert alert-danger rounded-4 border-0">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    This delivery is currently
                    <strong>
                        <?= dabbawala_view_e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $current_status
                                )
                            )
                        ) ?>
                    </strong>.

                    <?php if (!empty($delivery['rejection_reason'])): ?>

                        <div class="mt-2">
                            Reason:
                            <?= dabbawala_view_e(
                                $delivery['rejection_reason']
                            ) ?>
                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <div class="position-relative">

                <?php foreach ($timeline as $status_key => $status_data): ?>

                    <?php
                    $status_index = array_search(
                        $status_key,
                        $timeline_keys,
                        true
                    );

                    $completed = $status_index <= $current_index;
                    $current = $status_key === $current_status;
                    ?>

                    <div class="d-flex position-relative mb-4">

                        <?php if ($status_index < count($timeline_keys) - 1): ?>

                            <div
                                class="position-absolute"
                                style="
                                    left:20px;
                                    top:42px;
                                    width:2px;
                                    height:45px;
                                    background:<?= $completed ? '#ff6b00' : '#dee2e6' ?>;
                                "
                            ></div>

                        <?php endif; ?>

                        <div
                            class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0
                            <?= $completed ? 'bg-warning text-dark' : 'bg-light text-muted' ?>"
                            style="width:42px;height:42px;"
                        >

                            <i class="bi <?= dabbawala_view_e($status_data['icon']) ?>"></i>

                        </div>

                        <div class="ms-3 pt-1">

                            <div
                                class="fw-semibold
                                <?= $current ? 'text-warning' : '' ?>"
                            >
                                <?= dabbawala_view_e($status_data['label']) ?>

                                <?php if ($current): ?>

                                    <span class="badge bg-warning text-dark rounded-pill ms-2">
                                        Current
                                    </span>

                                <?php endif; ?>

                            </div>

                            <?php
                            $matching_tracking = null;

                            foreach ($tracking_history as $tracking) {
                                if (
                                    strtolower($tracking['status']) ===
                                    $status_key
                                ) {
                                    $matching_tracking = $tracking;
                                }
                            }
                            ?>

                            <?php if ($matching_tracking): ?>

                                <small class="text-muted">

                                    <?= dabbawala_view_e(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime(
                                                $matching_tracking['updated_at']
                                            )
                                        )
                                    ) ?>

                                    <?php if (!empty($matching_tracking['remarks'])): ?>

                                        <span class="d-block mt-1">
                                            <?= dabbawala_view_e(
                                                $matching_tracking['remarks']
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </small>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/dabbawala_footer.php';
?>