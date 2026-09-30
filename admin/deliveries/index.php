<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Deliveries';
$active_page = 'deliveries';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$statuses = [
    'assigned',
    'accepted',
    'rejected',
    'completed',
    'cancelled'
];

if (!in_array($status, $statuses, true)) {
    $status = '';
}

$sql = "
    SELECT
        da.id,
        da.order_id,
        da.dabbawala_id,
        da.assigned_at,
        da.assignment_status,
        o.order_number,
        u.name AS customer_name,
        d.name AS dabbawala_name
    FROM delivery_assignments da
    INNER JOIN orders o ON da.order_id = o.id
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN dabbawalas d ON da.dabbawala_id = d.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        AND (
            o.order_number LIKE ?
            OR u.name LIKE ?
            OR d.name LIKE ?
        )
    ";

    $value = '%' . $search . '%';

    $params = [
        $value,
        $value,
        $value
    ];

    $types = 'sss';
}

if ($status !== '') {

    $sql .= " AND da.assignment_status = ? ";

    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY da.assigned_at DESC ";

$assignments = [];
$error = '';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL ERROR: " . $conn->error);
}

if ($stmt) {

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }

    } else {
        $error = 'Unable to load deliveries.';
    }

    $stmt->close();

} else {
    $error = 'Unable to prepare delivery query.';
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex justify-content-between align-items-center">

        <div>
            <h2>Deliveries</h2>
            <p class="text-muted">
                Manage delivery assignments and tracking.
            </p>
        </div>

        <a
            href="/mumbaiDabbawala/admin/deliveries/assign.php"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Assign Delivery
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">
        <?php echo e($error); ?>
    </div>

<?php endif; ?>


<div class="admin-data-card mb-4">

    <div class="card-body">

        <form method="GET" class="row g-3">

            <div class="col-md-7">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Order number, customer or dabbawala"
                    value="<?php echo e($search); ?>"
                >

            </div>

            <div class="col-md-3">

                <select name="status" class="form-select">

                    <option value="">
                        All Statuses
                    </option>

                    <?php foreach ($statuses as $item): ?>

                        <option
                            value="<?php echo e($item); ?>"
                            <?php echo $status === $item ? 'selected' : ''; ?>
                        >
                            <?php
                            echo e(
                                ucwords(
                                    str_replace('_', ' ', $item)
                                )
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <button class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                    Search
                </button>

            </div>

        </form>

    </div>

</div>


<div class="admin-data-card">

    <div class="admin-data-card-header">

        <h5 class="mb-0">
            Delivery Assignments
        </h5>

    </div>

    <div class="card-body p-0">

        <?php if ($assignments): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">Order</th>
                            <th>Customer</th>
                            <th>Dabbawala</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Actions</th>
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

                                <span class="badge bg-secondary">
                                    <?php
                                    echo e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $row['assignment_status']
                                            )
                                        )
                                    );
                                    ?>
                                </span>

                            </td>

                            <td>
                                <?php echo e($row['assigned_at']); ?>
                            </td>

                            <td>

                                <a
                                    href="view.php?id=<?php echo (int)$row['id']; ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>

                                <a
                                    href="tracking.php?id=<?php echo (int)$row['id']; ?>"
                                    class="btn btn-sm btn-outline-success"
                                >
                                    <i class="bi bi-geo-alt"></i>
                                </a>

                                <a
                                    href="assign.php?edit=<?php echo (int)$row['id']; ?>"
                                    class="btn btn-sm btn-outline-warning"
                                >
                                    <i class="bi bi-arrow-repeat"></i>
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
                    <i class="bi bi-truck"></i>
                </div>

                <h5>No deliveries found</h5>

                <p class="text-muted mb-0">
                    Delivery assignments will appear here.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>