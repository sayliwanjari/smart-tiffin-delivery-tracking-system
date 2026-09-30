<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Subscriptions';
$active_page = 'subscriptions';


// ==========================================================
// HELPER
// ==========================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ==========================================================
// VARIABLES
// ==========================================================

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$error_message = '';

$rows = [];


// ==========================================================
// ALLOWED STATUS
// ==========================================================

$allowed_statuses = [
    'active',
    'paused',
    'expired',
    'cancelled'
];

if (
    $status !== '' &&
    !in_array($status, $allowed_statuses, true)
) {
    $status = '';
}


// ==========================================================
// FETCH SUBSCRIPTIONS
// ==========================================================

$sql = "
    SELECT
        s.id,
        s.user_id,
        s.plan_id,
        s.start_date,
        s.end_date,
        s.meals_remaining,
        s.status,
        s.created_at,

        u.name AS customer_name,
        u.email AS customer_email,

        p.name AS plan_name,
        p.price AS plan_price

    FROM subscriptions s

    INNER JOIN users u
        ON s.user_id = u.id

    INNER JOIN subscription_plans p
        ON s.plan_id = p.id

    WHERE 1 = 1
";


// ==========================================================
// SEARCH
// ==========================================================

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR p.name LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'sss';
}


// ==========================================================
// STATUS FILTER
// ==========================================================

if ($status !== '') {

    $sql .= " AND s.status = ? ";

    $params[] = $status;

    $types .= 's';
}


$sql .= " ORDER BY s.created_at DESC ";


// ==========================================================
// PREPARE
// ==========================================================

try {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            $conn->error
        );
    }


    // ======================================================
    // BIND PARAMETERS
    // ======================================================

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }


    // ======================================================
    // EXECUTE
    // ======================================================

    if (!$stmt->execute()) {

        throw new Exception(
            $stmt->error
        );
    }


    // ======================================================
    // RESULT
    // ======================================================

    $result = $stmt->get_result();

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $rows[] = $row;
        }

        $result->free();
    }


    $stmt->close();


} catch (Throwable $e) {

    $error_message =
        'Unable to load subscriptions: ' .
        $e->getMessage();
}


// ==========================================================
// HEADER
// ==========================================================

require_once __DIR__ . '/../includes/admin_header.php';

?>


<!-- ======================================================
     PAGE HEADER
====================================================== -->

<div class="admin-page-header">

    <div>

        <h2>
            Subscriptions
        </h2>

        <p class="text-muted">
            View customer subscription records.
        </p>

    </div>

</div>


<!-- ======================================================
     ERROR
====================================================== -->

<?php if ($error_message !== ''): ?>

    <div class="alert alert-danger">

        <i class="bi bi-exclamation-triangle me-2"></i>

        <strong>Database Error:</strong>

        <?php echo e($error_message); ?>

    </div>

<?php endif; ?>


<!-- ======================================================
     SEARCH / FILTER
====================================================== -->

<div class="admin-data-card mb-4">

    <div class="card-body">

        <form
            method="GET"
            class="row g-3 align-items-end"
        >

            <div class="col-md-7">

                <label class="form-label">
                    Search
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Customer name, email or plan"
                    value="<?php echo e($search); ?>"
                >

            </div>


            <div class="col-md-3">

                <label class="form-label">
                    Status
                </label>

                <select
                    name="status"
                    class="form-select"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <?php foreach ($allowed_statuses as $item): ?>

                        <option
                            value="<?php echo e($item); ?>"
                            <?php echo $status === $item ? 'selected' : ''; ?>
                        >
                            <?php echo e(ucfirst($item)); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-md-2">

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >

                    <i class="bi bi-search me-1"></i>

                    Search

                </button>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================
     SUBSCRIPTION LIST
====================================================== -->

<div class="admin-data-card">

    <div class="admin-data-card-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="mb-1">
                    Subscription List
                </h5>

                <small class="text-muted">

                    <?php echo number_format(count($rows)); ?>

                    result<?php echo count($rows) === 1 ? '' : 's'; ?>

                </small>

            </div>

            <span class="badge bg-warning text-dark">

                <i class="bi bi-card-checklist me-1"></i>

                <?php echo number_format(count($rows)); ?>

            </span>

        </div>

    </div>


    <div class="card-body p-0">


        <?php if (!empty($rows)): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                Customer
                            </th>

                            <th>
                                Plan
                            </th>

                            <th>
                                Start
                            </th>

                            <th>
                                End
                            </th>

                            <th>
                                Meals Left
                            </th>

                            <th>
                                Status
                            </th>

                            <th class="text-center">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($rows as $row): ?>

                            <tr>

                                <!-- CUSTOMER -->

                                <td class="px-4">

                                    <div class="fw-semibold">

                                        <?php
                                        echo e(
                                            $row['customer_name']
                                        );
                                        ?>

                                    </div>

                                    <small class="text-muted">

                                        <?php
                                        echo e(
                                            $row['customer_email']
                                        );
                                        ?>

                                    </small>

                                </td>


                                <!-- PLAN -->

                                <td>

                                    <div class="fw-semibold">

                                        <?php
                                        echo e(
                                            $row['plan_name']
                                        );
                                        ?>

                                    </div>

                                    <?php if (
                                        isset($row['plan_price'])
                                    ): ?>

                                        <small class="text-muted">

                                            ₹<?php
                                            echo number_format(
                                                (float) $row['plan_price'],
                                                2
                                            );
                                            ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <!-- START -->

                                <td>

                                    <?php
                                    echo e(
                                        $row['start_date']
                                    );
                                    ?>

                                </td>


                                <!-- END -->

                                <td>

                                    <?php
                                    echo e(
                                        $row['end_date']
                                    );
                                    ?>

                                </td>


                                <!-- MEALS -->

                                <td>

                                    <span class="fw-semibold">

                                        <?php
                                        echo (int)
                                            $row['meals_remaining'];
                                        ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $status_class = 'bg-primary';

                                    if (
                                        $row['status'] === 'active'
                                    ) {

                                        $status_class = 'bg-success';

                                    } elseif (
                                        $row['status'] === 'paused'
                                    ) {

                                        $status_class = 'bg-warning text-dark';

                                    } elseif (
                                        $row['status'] === 'expired'
                                    ) {

                                        $status_class = 'bg-secondary';

                                    } elseif (
                                        $row['status'] === 'cancelled'
                                    ) {

                                        $status_class = 'bg-danger';
                                    }

                                    ?>

                                    <span
                                        class="badge <?php echo $status_class; ?>"
                                    >

                                        <?php
                                        echo e(
                                            ucfirst(
                                                $row['status']
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- VIEW -->

                                <td class="text-center">

                                    <a
                                        href="view.php?id=<?php echo (int) $row['id']; ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View Subscription"
                                    >

                                        <i class="bi bi-eye"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="admin-empty-state text-center p-5">

                <div class="admin-empty-state-icon mb-3">

                    <i class="bi bi-card-checklist fs-1"></i>

                </div>

                <h5>
                    No subscriptions found
                </h5>

                <p class="text-muted mb-0">

                    <?php if (
                        $search !== '' ||
                        $status !== ''
                    ): ?>

                        No subscriptions match your search or filter.

                    <?php elseif (
                        $error_message === ''
                    ): ?>

                        There are currently no subscriptions in the system.

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>


    </div>

</div>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>