<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Reports';
$active_page = 'reports';

function countRows($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}


$total_customers = countRows(
    $conn,
    "SELECT COUNT(*) total FROM users"
);

$total_dabbawalas = countRows(
    $conn,
    "SELECT COUNT(*) total FROM dabbawalas"
);

$total_orders = countRows(
    $conn,
    "SELECT COUNT(*) total FROM orders"
);

$cancelled_orders = countRows(
    $conn,
    "SELECT COUNT(*) total FROM orders WHERE order_status='cancelled'"
);

$pending_deliveries = countRows(
    $conn,
    "
    SELECT COUNT(*) total
    FROM delivery_assignments
    WHERE assignment_status IN ('assigned','accepted')
    "
);

$completed_deliveries = countRows(
    $conn,
    "
    SELECT COUNT(*) total
    FROM delivery_assignments
    WHERE assignment_status='completed'
    "
);

$active_subscriptions = countRows(
    $conn,
    "
    SELECT COUNT(*) total
    FROM subscriptions
    WHERE status='active'
    "
);


$revenue = 0;

$result = $conn->query("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN payment_status = 'success'
                THEN amount
                ELSE 0
            END
        ),
        0
    ) AS revenue
    FROM payments
");

if ($result) {

    $row = $result->fetch_assoc();

    $revenue = (float)$row['revenue'];

}


$order_status_data = [];

$result = $conn->query("
    SELECT
        order_status,
        COUNT(*) AS total
    FROM orders
    GROUP BY order_status
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $order_status_data[] = $row;
    }

}


$monthly_data = [];

$result = $conn->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS orders,
        COALESCE(SUM(total_amount),0) AS revenue
    FROM orders
    WHERE created_at >= DATE_SUB(
        CURRENT_DATE,
        INTERVAL 6 MONTH
    )
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $monthly_data[] = $row;
    }

}


require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <h2>Reports & Analytics</h2>

    <p class="text-muted">
        Overview of Smart Tiffin operations.
    </p>

</div>


<div class="row g-4 mb-4">

    <?php

    $cards = [
        ['Customers', $total_customers, 'bi-people'],
        ['Dabbawalas', $total_dabbawalas, 'bi-person-badge'],
        ['Orders', $total_orders, 'bi-cart3'],
        ['Revenue', '₹' . number_format($revenue, 2), 'bi-currency-rupee'],
        ['Pending Deliveries', $pending_deliveries, 'bi-truck'],
        ['Completed Deliveries', $completed_deliveries, 'bi-check-circle'],
        ['Active Subscriptions', $active_subscriptions, 'bi-card-checklist'],
        ['Cancelled Orders', $cancelled_orders, 'bi-x-circle']
    ];

    foreach ($cards as $card):

    ?>

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">
                                <?php echo htmlspecialchars($card[0], ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                            <h3 class="fw-bold mb-0">
                                <?php echo htmlspecialchars((string)$card[1], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>

                        </div>

                        <i class="bi <?php echo htmlspecialchars($card[2], ENT_QUOTES, 'UTF-8'); ?> fs-3 text-warning"></i>

                    </div>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>


<div class="row g-4">

    <div class="col-lg-6">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white">
                <strong>Orders by Status</strong>
            </div>

            <div class="card-body">

                <?php if ($order_status_data): ?>

                    <canvas id="orderStatusChart"></canvas>

                <?php else: ?>

                    <div class="text-center text-muted py-5">
                        No order data available.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div class="col-lg-6">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white">
                <strong>Orders & Revenue</strong>
            </div>

            <div class="card-body">

                <?php if ($monthly_data): ?>

                    <canvas id="monthlyChart"></canvas>

                <?php else: ?>

                    <div class="text-center text-muted py-5">
                        No monthly data available.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

<?php if ($order_status_data): ?>

new Chart(
    document.getElementById('orderStatusChart'),
    {
        type: 'doughnut',

        data: {
            labels: <?php
                echo json_encode(
                    array_column(
                        $order_status_data,
                        'order_status'
                    )
                );
            ?>,

            datasets: [
                {
                    data: <?php
                        echo json_encode(
                            array_map(
                                'intval',
                                array_column(
                                    $order_status_data,
                                    'total'
                                )
                            )
                        );
                    ?>
                }
            ]
        },

        options: {
            responsive: true
        }
    }
);

<?php endif; ?>


<?php if ($monthly_data): ?>

new Chart(
    document.getElementById('monthlyChart'),
    {
        type: 'bar',

        data: {

            labels: <?php
                echo json_encode(
                    array_column(
                        $monthly_data,
                        'month'
                    )
                );
            ?>,

            datasets: [

                {
                    label: 'Orders',

                    data: <?php
                        echo json_encode(
                            array_map(
                                'intval',
                                array_column(
                                    $monthly_data,
                                    'orders'
                                )
                            )
                        );
                    ?>
                },

                {
                    label: 'Revenue',

                    data: <?php
                        echo json_encode(
                            array_map(
                                'floatval',
                                array_column(
                                    $monthly_data,
                                    'revenue'
                                )
                            )
                        );
                    ?>
                }

            ]
        },

        options: {
            responsive: true
        }
    }
);

<?php endif; ?>

</script>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>