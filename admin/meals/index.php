<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';



$page_title = 'Meals';
$active_page = 'meals';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_token'];

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error = 'Invalid request.';

    } else {

        $action = $_POST['action'] ?? '';

        $id = filter_var(
            $_POST['id'] ?? '',
            FILTER_VALIDATE_INT
        );


        if ($action === 'delete' && $id) {

            $stmt = $conn->prepare("
                DELETE FROM meals
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param('i', $id);

                if ($stmt->execute()) {

                    if ($stmt->affected_rows) {
                        $success = 'Meal deleted successfully.';
                    } else {
                        $error = 'Meal not found.';
                    }

                } else {

                    if ($conn->errno === 1451) {

                        $error =
                            'This meal cannot be deleted because it is used by an order.';

                    } else {

                        $error =
                            'Unable to delete meal.';
                    }
                }

                $stmt->close();
            }

        }


        if ($action === 'toggle' && $id) {

            $stmt = $conn->prepare("
                UPDATE meals
                SET is_available = NOT is_available
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param('i', $id);

                if ($stmt->execute()) {
                    $success =
                        'Meal availability updated.';
                } else {
                    $error =
                        'Unable to update availability.';
                }

                $stmt->close();
            }
        }
    }
}


$sql = "
    SELECT
        id,
        name,
        description,
        price,
        image,
        is_available,
        created_at
    FROM meals
";

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        WHERE name LIKE ?
        OR description LIKE ?
    ";

    $v = '%' . $search . '%';

    $params = [$v, $v];
    $types = 'ss';
}

$sql .= " ORDER BY created_at DESC ";

$meals = [];

$stmt = $conn->prepare($sql);

if ($stmt) {

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $meals[] = $row;
    }

    $stmt->close();
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex justify-content-between align-items-center">

        <div>

            <h2>Meals</h2>

            <p class="text-muted">
                Manage Smart Tiffin menu items.
            </p>

        </div>

        <a
            href="/mumbaiDabbawala/admin/meals/add.php"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Add Meal
        </a>

    </div>

</div>


<?php if ($success): ?>

    <div class="alert alert-success">
        <?php echo e($success); ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-danger">
        <?php echo e($error); ?>
    </div>

<?php endif; ?>


<div class="admin-data-card mb-4">

    <div class="card-body">

        <form method="GET" class="d-flex gap-2">

            <input
                name="search"
                class="form-control"
                placeholder="Search meals..."
                value="<?php echo e($search); ?>"
            >

            <button class="btn btn-primary">
                Search
            </button>

        </form>

    </div>

</div>


<div class="admin-data-card">

    <div class="card-body p-0">

        <?php if ($meals): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">Meal</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Availability</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($meals as $meal): ?>

                        <tr>

                            <td class="px-4">

                                <strong>
                                    <?php echo e($meal['name']); ?>
                                </strong>

                            </td>

                            <td>

                                <?php
                                echo e(
                                    mb_strimwidth(
                                        $meal['description'] ?? '',
                                        0,
                                        60,
                                        '...'
                                    )
                                );
                                ?>

                            </td>

                            <td>
                                ₹<?php echo number_format((float)$meal['price'], 2); ?>
                            </td>

                            <td>

                                <?php if ($meal['is_available']): ?>

                                    <span class="badge bg-success">
                                        Available
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Unavailable
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php echo e($meal['created_at']); ?>
                            </td>

                            <td>

                                <a
                                    href="edit.php?id=<?php echo (int)$meal['id']; ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>


                                <form
                                    method="POST"
                                    class="d-inline"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($csrf); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int)$meal['id']; ?>"
                                    >

                                    <button
                                        class="btn btn-sm btn-outline-warning"
                                        title="Toggle availability"
                                    >
                                        <i class="bi bi-toggle-on"></i>
                                    </button>

                                </form>


                                <form
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Delete this meal?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($csrf); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int)$meal['id']; ?>"
                                    >

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="admin-empty-state">

                <div class="admin-empty-state-icon">
                    <i class="bi bi-egg-fried"></i>
                </div>

                <h5>No meals found</h5>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>