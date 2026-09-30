<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/includes/dabbawala_auth.php';

$page_title = 'Dabbawala Dashboard';
$active_page = 'dashboard';

$today = date('Y-m-d');

$today_deliveries = [];
$stats = [
    'today' => 0,
    'pending' => 0,
    'completed' => 0
];

$error_message = '';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

try {

    /*
     * Today's total deliveries
     */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM delivery_assignments da
        INNER JOIN orders o
            ON o.id = da.order_id
        WHERE da.dabbawala_id = ?
          AND DATE(o.delivery_date) = ?
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "is",
        $dabbawala_id,
        $today
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stats['today'] = (int) $row['total'];
    }

    $stmt->close();


    /*
     * Pending deliveries
     */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM delivery_assignments da
        INNER JOIN orders o
            ON o.id = da.order_id
        WHERE da.dabbawala_id = ?
          AND DATE(o.delivery_date) = ?
          AND da.assignment_status IN ('assigned', 'accepted')
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "is",
        $dabbawala_id,
        $today
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stats['pending'] = (int) $row['total'];
    }

    $stmt->close();


    /*
     * Completed deliveries
     */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM delivery_assignments da
        INNER JOIN orders o
            ON o.id = da.order_id
        WHERE da.dabbawala_id = ?
          AND DATE(o.delivery_date) = ?
          AND da.assignment_status = 'completed'
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "is",
        $dabbawala_id,
        $today
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stats['completed'] = (int) $row['total'];
    }

    $stmt->close();


    /*
     * Today's deliveries
     */
    $stmt = $conn->prepare("
        SELECT
            da.id AS assignment_id,
            da.assignment_status,

            o.id AS order_id,
            o.order_number,
            o.delivery_date,
            o.delivery_time,
            o.order_status,

            u.name AS customer_name,

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
          AND DATE(o.delivery_date) = ?

        ORDER BY
            o.delivery_time ASC,
            da.id DESC
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "is",
        $dabbawala_id,
        $today
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $today_deliveries[] = $row;
    }

    $stmt->close();

} catch (Throwable $e) {

    $error_message = 'Unable to load dashboard information right now.';
}

require_once __DIR__ . '/includes/dabbawala_header.php';

?>

<div class="page-heading">

    <h1>
        Good day, <?= dabbawala_e($dabbawala_name); ?> 👋
    </h1>

    <p>
        Here is your delivery schedule for today.
    </p>

</div>

<?php if ($error_message !== ''): ?>

    <div class="alert alert-danger border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= dabbawala_e($error_message); ?>
    </div>

<?php endif; ?>


<!-- Statistics -->

<div class="row g-4 mb-4">

    <div class="col-12 col-md-4">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Today's Deliveries
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?= (int) $stats['today']; ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 p-3"
                        style="background:#fff1e8;color:#ff6b00;"
                    >
                        <i class="bi bi-box-seam-fill fs-4"></i>
                    </div>

                </div>

                <div class="mt-3 small text-muted">
                    Deliveries assigned for today
                </div>

            </div>

        </div>

    </div>


    <div class="col-12 col-md-4">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Pending
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?= (int) $stats['pending']; ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 p-3"
                        style="background:#fff7df;color:#f59e0b;"
                    >
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>

                </div>

                <div class="mt-3 small text-muted">
                    Deliveries waiting for completion
                </div>

            </div>

        </div>

    </div>


    <div class="col-12 col-md-4">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Completed
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?= (int) $stats['completed']; ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 p-3"
                        style="background:#e9f8ef;color:#198754;"
                    >
                        <i class="bi bi-check-circle-fill fs-4"></i>
                    </div>

                </div>

                <div class="mt-3 small text-muted">
                    Successfully completed today
                </div>

            </div>

        </div>

    </div>

</div>


<!-- Availability -->

<div class="card border-0 shadow-sm mb-4">

    <div class="card-body p-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

            <div>

                <h5 class="fw-bold mb-1">
                    <i class="bi bi-person-check me-2"></i>
                    Delivery Availability
                </h5>

                <p class="text-muted mb-0">
                    Your current availability status
                </p>

            </div>

            <div>

                <?php
                $availability = strtolower(
                    (string) ($dabbawala_availability_status ?? 'available')
                );

                if ($availability === 'available') {
                    $badge_class = 'bg-success';
                    $availability_text = 'Available';
                    $icon = 'bi-check-circle-fill';
                } elseif ($availability === 'busy') {
                    $badge_class = 'bg-warning text-dark';
                    $availability_text = 'Busy';
                    $icon = 'bi-clock-fill';
                } else {
                    $badge_class = 'bg-secondary';
                    $availability_text = 'Unavailable';
                    $icon = 'bi-dash-circle-fill';
                }
                ?>

                <span class="badge <?= $badge_class; ?> px-3 py-2">
                    <i class="bi <?= $icon; ?> me-1"></i>
                    <?= dabbawala_e($availability_text); ?>
                </span>

            </div>

        </div>

    </div>

</div>


<!-- Today's Deliveries -->

<div class="card border-0 shadow-sm">

    <div class="card-body p-0">

        <div class="p-4 border-bottom">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="fw-bold mb-1">
                        Today's Assigned Deliveries
                    </h5>

                    <p class="text-muted mb-0 small">
                        Your delivery schedule for <?= date('d M Y'); ?>
                    </p>

                </div>

                <a
                    href="/mumbaiDabbawala/dabbawalas/delivaries/view.php"
                    class="btn btn-outline-dark btn-sm"
                >
                    View All
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>

            </div>

        </div>


        <?php if (empty($today_deliveries)): ?>

            <div class="text-center py-5 px-3">

                <div
                    class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                    style="
                        width:70px;
                        height:70px;
                        background:#fff1e8;
                        color:#ff6b00;
                    "
                >
                    <i class="bi bi-box-seam fs-2"></i>
                </div>

                <h5 class="fw-bold">
                    No deliveries for today
                </h5>

                <p class="text-muted mb-0">
                    You currently don't have any deliveries assigned for today.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">Order</th>
                            <th>Customer</th>
                            <th>Delivery Address</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th class="text-end px-4">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($today_deliveries as $delivery): ?>

                        <?php

                        $status = strtolower(
                            (string) $delivery['assignment_status']
                        );

                        switch ($status) {

                            case 'accepted':
                                $status_class = 'bg-primary';
                                break;

                            case 'completed':
                                $status_class = 'bg-success';
                                break;

                            case 'rejected':
                            case 'cancelled':
                                $status_class = 'bg-danger';
                                break;

                            default:
                                $status_class = 'bg-warning text-dark';
                                break;
                        }

                        $address_parts = array_filter([
                            $delivery['address_line'] ?? '',
                            $delivery['landmark'] ?? '',
                            $delivery['city'] ?? ''
                        ]);

                        $short_address = implode(', ', $address_parts);

                        ?>

                        <tr>

                            <td class="px-4">

                                <strong>
                                    <?= dabbawala_e(
                                        $delivery['order_number']
                                    ); ?>
                                </strong>

                            </td>


                            <td>

                                <div class="fw-semibold">
                                    <?= dabbawala_e(
                                        $delivery['customer_name']
                                    ); ?>
                                </div>

                            </td>


                            <td>

                                <div
                                    class="text-muted"
                                    style="max-width:240px;"
                                >
                                    <i class="bi bi-geo-alt me-1"></i>

                                    <?= dabbawala_e(
                                        $short_address ?: 'Address unavailable'
                                    ); ?>
                                </div>

                            </td>


                            <td>

                                <span class="fw-semibold">

                                    <i class="bi bi-clock me-1"></i>

                                    <?= dabbawala_e(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $delivery['delivery_time']
                                            )
                                        )
                                    ); ?>

                                </span>

                            </td>


                            <td>

                                <span class="badge <?= $status_class; ?> px-3 py-2">

                                    <?= dabbawala_e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $status
                                            )
                                        )
                                    ); ?>

                                </span>

                            </td>


                            <td class="text-end px-4">

                                <a
                                    href="/mumbaiDabbawala/dabbawalas/deliveries/view.php?id=<?= (int) $delivery['assignment_id']; ?>"
                                    class="btn btn-sm btn-outline-dark"
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

        <?php endif; ?>

    </div>

</div>


<?php require_once __DIR__ . '/includes/dabbawala_footer.php'; ?>