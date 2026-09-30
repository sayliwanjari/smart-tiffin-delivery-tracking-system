<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Delivery Tracking';
$active_page = 'deliveries';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$assignments = [];

$stmt = $conn->prepare("
    SELECT
        da.id,
        o.order_number,
        u.name AS customer_name,
        d.name AS dabbawala_name,
        da.assignment_status,
        (
            SELECT dt.status
            FROM delivery_tracking dt
            WHERE dt.assignment_id = da.id
            ORDER BY dt.id DESC
            LIMIT 1
        ) AS latest_status,
        (
            SELECT dt.updated_at
            FROM delivery_tracking dt
            WHERE dt.assignment_id = da.id
            ORDER BY dt.id DESC
            LIMIT 1
        ) AS latest_update
    FROM delivery_assignments da
    INNER JOIN orders o ON da.order_id = o.id
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN dabbawalas d ON da.dabbawala_id = d.id
    ORDER BY da.assigned_at DESC
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }

    $stmt->close();
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <h2>Delivery Tracking</h2>

    <p class="text-muted">
        Monitor current delivery statuses.
    </p>

</div>


<div class="admin-data-card">

    <div class="card-body p-0">

        <?php if ($assignments): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">Order</th>
                            <th>Customer</th>
                            <th>Dabbawala</th>
                            <th>Assignment</th>
                            <th>Tracking</th>
                            <th>Last Update</th>
                            <th></th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($assignments as $row): ?>

                        <tr>

                            <td class="px-4 fw-semibold">
                                <?php echo e($row['order_number']); ?>
                            </td>

                            <td>
                                <?php echo e($row['customer_name']); ?>
                            </td>

                            <td>
                                <?php echo e($row['dabbawala_name']); ?>
                            </td>

                            <td>
                                <?php echo e($row['assignment_status']); ?>
                            </td>

                            <td>

                                <?php if ($row['latest_status']): ?>

                                    <span class="badge bg-primary">
                                        <?php
                                        echo e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $row['latest_status']
                                                )
                                            )
                                        );
                                        ?>
                                    </span>

                                <?php else: ?>

                                    <span class="text-muted">
                                        No update
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php echo e($row['latest_update'] ?? '—'); ?>
                            </td>

                            <td>

                                <a
                                    href="map.php?id=<?php echo (int)$row['id']; ?>"
                                    class="btn btn-sm btn-outline-success"
                                >
                                    <i class="bi bi-geo-alt"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="admin-empty-state">

                <div class="admin-empty-state-icon">
                    <i class="bi bi-geo-alt"></i>
                </div>

                <h5>No active tracking data</h5>

                <p class="text-muted">
                    GPS and tracking information will appear here
                    when deliveries begin.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>