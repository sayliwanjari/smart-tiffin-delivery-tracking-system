<?php

require_once __DIR__ . '/includes/dabbawala_auth.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'Delivery History';
$active_page = 'history';

$dabbawala_id = (int) $dabbawala_id;

$history = [];

$sql = "
    SELECT
        da.id AS assignment_id,
        da.order_id,
        da.assignment_status,
        da.completed_at,
        da.updated_at AS assignment_updated_at,

        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.order_status,

        u.name AS customer_name,
        u.phone AS customer_phone

    FROM delivery_assignments da

    INNER JOIN orders o
        ON o.id = da.order_id

    INNER JOIN users u
        ON u.id = o.user_id

    WHERE da.dabbawala_id = ?
      AND (
            da.assignment_status IN ('delivered', 'completed')
            OR o.order_status = 'completed'
          )

    ORDER BY
        COALESCE(da.completed_at, da.updated_at, o.delivery_date) DESC,
        da.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $dabbawala_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    $stmt->close();
}

function dabbawala_history_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function history_status_class($status)
{
    switch (strtolower((string) $status)) {
        case 'delivered':
        case 'completed':
            return 'bg-success-subtle text-success';

        case 'cancelled':
        case 'failed':
            return 'bg-danger-subtle text-danger';

        default:
            return 'bg-secondary-subtle text-secondary';
    }
}

require_once __DIR__ . '/includes/dabbawala_header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <div class="text-muted small mb-1">
                <i class="bi bi-clock-history me-1"></i>
                Delivery Records
            </div>

            <h2 class="fw-bold mb-1">
                Delivery History
            </h2>

            <p class="text-muted mb-0">
                View your previously completed deliveries.
            </p>
        </div>

        <div class="bg-white rounded-4 shadow-sm px-4 py-3">
            <div class="small text-muted">
                Total Deliveries
            </div>

            <div class="fs-4 fw-bold">
                <?php echo count($history); ?>
            </div>
        </div>

    </div>


    <?php if (empty($history)): ?>

        <!-- Empty State -->
        <div class="bg-white rounded-4 shadow-sm text-center py-5 px-4">

            <div
                class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                style="width:80px;height:80px;background:#fff3e8;"
            >
                <i
                    class="bi bi-clock-history fs-1"
                    style="color:#ff6b00;"
                ></i>
            </div>

            <h4 class="fw-bold">
                No delivery history yet
            </h4>

            <p class="text-muted mb-0">
                Your completed deliveries will appear here.
            </p>

        </div>

    <?php else: ?>

        <!-- Desktop Table -->
        <div class="bg-white rounded-4 shadow-sm overflow-hidden">

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th>Customer</th>
                            <th>Delivery Date</th>
                            <th>Delivered Time</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($history as $item): ?>

                        <?php
                        $status = $item['assignment_status'];

                        $delivered_time = $item['completed_at']
                            ?: $item['assignment_updated_at'];
                        ?>

                        <tr>

                            <td class="px-4">

                                <div class="fw-semibold">
                                    #<?php echo dabbawala_history_e($item['order_number']); ?>
                                </div>

                                <small class="text-muted">
                                    Order ID:
                                    <?php echo (int) $item['order_id']; ?>
                                </small>

                            </td>


                            <td>

                                <div class="fw-semibold">
                                    <?php echo dabbawala_history_e($item['customer_name']); ?>
                                </div>

                                <small class="text-muted">
                                    <i class="bi bi-telephone me-1"></i>
                                    <?php echo dabbawala_history_e($item['customer_phone']); ?>
                                </small>

                            </td>


                            <td>
                                <?php
                                echo !empty($item['delivery_date'])
                                    ? date('d M Y', strtotime($item['delivery_date']))
                                    : '—';
                                ?>
                            </td>


                            <td>

                                <?php if (!empty($delivered_time)): ?>

                                    <?php
                                    echo date(
                                        'd M Y, h:i A',
                                        strtotime($delivered_time)
                                    );
                                    ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="badge rounded-pill px-3 py-2 <?php
                                    echo history_status_class($status);
                                ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </span>

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
require_once __DIR__ . '/includes/dabbawala_footer.php';
?>