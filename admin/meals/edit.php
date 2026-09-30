<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Edit Meal';
$active_page = 'meals';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        price,
        is_available
    FROM meals
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param('i', $id);
$stmt->execute();

$result = $stmt->get_result();

$meal = $result->fetch_assoc();

$stmt->close();

if (!$meal) {
    header('Location: index.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error = 'Invalid request.';

    } else {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = $_POST['price'] ?? '';
        $available =
            isset($_POST['is_available']) ? 1 : 0;


        if ($name === '') {

            $error = 'Meal name is required.';

        } elseif (
            !is_numeric($price)
            || (float)$price < 0
        ) {

            $error = 'Enter a valid price.';

        } else {

            $price_value = (float)$price;

            $stmt = $conn->prepare("
                UPDATE meals
                SET
                    name = ?,
                    description = ?,
                    price = ?,
                    is_available = ?
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    'ssdii',
                    $name,
                    $description,
                    $price_value,
                    $available,
                    $id
                );

                if ($stmt->execute()) {

                    header(
                        'Location: index.php?updated=1'
                    );

                    exit;

                } else {

                    $error =
                        'Unable to update meal.';
                }

                $stmt->close();
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <h2>Edit Meal</h2>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">
        <?php echo e($error); ?>
    </div>

<?php endif; ?>


<div class="admin-data-card">

    <div class="card-body">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION['csrf_token']); ?>"
            >


            <div class="mb-3">

                <label class="form-label">
                    Meal Name
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?php echo e($meal['name']); ?>"
                    required
                >

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Description
                </label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="4"
                ><?php echo e($meal['description']); ?></textarea>

            </div>


            <div class="mb-3">

                <label class="form-label">
                    Price
                </label>

                <input
                    type="number"
                    name="price"
                    class="form-control"
                    min="0"
                    step="0.01"
                    value="<?php echo e($meal['price']); ?>"
                    required
                >

            </div>


            <div class="form-check mb-4">

                <input
                    type="checkbox"
                    name="is_available"
                    class="form-check-input"
                    id="available"
                    <?php
                    echo $meal['is_available']
                        ? 'checked'
                        : '';
                    ?>
                >

                <label
                    class="form-check-label"
                    for="available"
                >
                    Available
                </label>

            </div>


            <button class="btn btn-primary">
                Update Meal
            </button>

            <a
                href="index.php"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>