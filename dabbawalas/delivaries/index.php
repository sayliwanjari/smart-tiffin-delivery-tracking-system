<?php

require_once __DIR__ . '/../includes/dabbawala_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'My Deliveries';
$active_page = 'deliveries';

function dabbawala_delivery_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$deliveries = [];
$error_message = '';

$sql = "
    SELECT
        da.id AS assignment_id,
        da.assignment_status,
        da.assigned_at,

        o.id AS order_id,
        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.total_amount,
        o.order_status,

        u.name AS customer_name,
        u.phone AS customer_phone,

        a.address_line,
        a.landmark,
        a.city,
        a.state,
        a.postal_code

    FROM delivery_assignments da

    INNER JOIN orders o
        ON o.id = da.order_id

    INNER JOIN users u
        ON u.id = o.user_id

    LEFT JOIN addresses a
        ON a.id = o.address_id

    WHERE da.dabbawala_id = ?

    ORDER BY
        o.delivery_date DESC,
        o.delivery_time ASC,
        da.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('i', $dabbawala_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $deliveries[] = $row;
        }
    } else {
        $error_message = 'Unable to load your deliveries.';
    }

    $stmt->close();
} else {
    $error_message = 'Unable to prepare the delivery query.';
}

require_once __DIR__ . '/../includes/dabbawala_header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge rounded-pill bg-warning text-dark px-3 py-2">
                    <i class="bi bi-box-seam me-1"></i>
                    Delivery Management
                </span>
            </div>

            <h2 class="fw-bold mb-1">My Deliveries</h2>

            <p class="text-muted mb-0">
                Manage and view all deliveries assigned to you.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="bg-white border rounded-4 px-3 py-2 shadow-sm">
                <div class="small text-muted">Total Deliveries</div>
                <div class="fw-bold fs-5">
                    <?= count($deliveries) ?>
                </div>
            </div>
        </div>

    </div>

    <?php if ($error_message): ?>

        <div class="alert alert-danger rounded-4 border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= dabbawala_delivery_e($error_message) ?>
        </div>

    <?php elseif (empty($deliveries)): ?>

        <!-- Empty State -->
        <div class="bg-white rounded-4 shadow-sm p-5 text-center">

            <div
                class="mx-auto mb-4 d-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10"
                style="width:90px;height:90px;"
            >
                <i class="bi bi-inbox fs-1 text-warning"></i>
            </div>

            <h4 class="fw-bold">No deliveries assigned</h4>

            <p class="text-muted mb-0">
                You currently don't have any deliveries assigned to you.
            </p>

        </div>

    <?php else: ?>

        <!-- Desktop Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">

            <div class="card-header bg-white border-0 p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>
                        <h5 class="fw-bold mb-1">
                            Assigned Deliveries
                        </h5>

                        <p class="text-muted small mb-0">
                            Deliveries currently assigned to your account
                        </p>
                    </div>

                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                        <?= count($deliveries) ?> Deliveries
                    </span>

                </div>

            </div>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">Order</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Delivery</th>
                            <th>Order Status</th>
                            <th>Assignment</th>
                            <th class="text-end px-4">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($deliveries as $delivery): ?>

                        <?php
                        $assignment_status = strtolower(
                            (string) $delivery['assignment_status']
                        );

                        $order_status = strtolower(
                            (string) $delivery['order_status']
                        );

                        $assignment_class = 'bg-secondary';

                        if ($assignment_status === 'accepted') {
                            $assignment_class = 'bg-primary';
                        } elseif ($assignment_status === 'rejected') {
                            $assignment_class = 'bg-danger';
                        } elseif ($assignment_status === 'completed') {
                            $assignment_class = 'bg-success';
                        } elseif ($assignment_status === 'pending') {
                            $assignment_class = 'bg-warning text-dark';
                        }

                        $order_class = 'bg-secondary';

                        if ($order_status === 'confirmed') {
                            $order_class = 'bg-primary';
                        } elseif ($order_status === 'completed') {
                            $order_class = 'bg-success';
                        } elseif ($order_status === 'cancelled') {
                            $order_class = 'bg-danger';
                        } elseif ($order_status === 'pending') {
                            $order_class = 'bg-warning text-dark';
                        }

                        $address_parts = [];

                        if (!empty($delivery['address_line'])) {
                            $address_parts[] = $delivery['address_line'];
                        }

                        if (!empty($delivery['landmark'])) {
                            $address_parts[] = $delivery['landmark'];
                        }

                        if (!empty($delivery['city'])) {
                            $address_parts[] = $delivery['city'];
                        }

                        $short_address = implode(', ', $address_parts);

                        if ($short_address === '') {
                            $short_address = 'Address not available';
                        }
                        ?>

                        <tr>

                            <!-- Order -->
                            <td class="px-4">

                                <div class="fw-bold">
                                    #<?= dabbawala_delivery_e($delivery['order_number']) ?>
                                </div>

                                <small class="text-muted">
                                    Assignment #<?= (int) $delivery['assignment_id'] ?>
                                </small>

                            </td>

                            <!-- Customer -->
                            <td>

                                <div class="fw-semibold">
                                    <?= dabbawala_delivery_e($delivery['customer_name']) ?>
                                </div>

                                <?php if (!empty($delivery['customer_phone'])): ?>

                                    <small class="text-muted">
                                        <i class="bi bi-telephone me-1"></i>
                                        <?= dabbawala_delivery_e($delivery['customer_phone']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <!-- Address -->
                            <td style="max-width:220px;">

                                <div class="small">

                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>

                                    <?= dabbawala_delivery_e($short_address) ?>

                                </div>

                            </td>

                            <!-- Delivery -->
                            <td>

                                <div class="fw-semibold">
                                    <?= dabbawala_delivery_e($delivery['delivery_date']) ?>
                                </div>

                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= dabbawala_delivery_e($delivery['delivery_time']) ?>
                                </small>

                            </td>

                            <!-- Order status -->
                            <td>

                                <span class="badge rounded-pill <?= $order_class ?> px-3 py-2">
                                    <?= dabbawala_delivery_e(ucwords(str_replace('_', ' ', $order_status))) ?>
                                </span>

                            </td>

                            <!-- Assignment -->
                            <td>

                                <span class="badge rounded-pill <?= $assignment_class ?> px-3 py-2">
                                    <?= dabbawala_delivery_e(ucwords(str_replace('_', ' ', $assignment_status))) ?>
                                </span>

                            </td>

                            <!-- Action -->
                            <td class="text-end px-4">

                                <a
                                    href="view.php?id=<?= (int) $delivery['assignment_id'] ?>"
                                    class="btn btn-sm btn-dark rounded-pill px-3"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/dabbawala_footer.php';
?>