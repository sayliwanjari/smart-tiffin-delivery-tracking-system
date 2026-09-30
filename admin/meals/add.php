<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Add Meal';
$active_page = 'meals';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

$name = '';
$description = '';
$price = '';
$is_available = 1;


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
        $price = trim($_POST['price'] ?? '');
        $is_available =
            isset($_POST['is_available']) ? 1 : 0;


        if ($name === '') {

            $error = 'Meal name is required.';

        } elseif (
            !is_numeric($price)
            || (float)$price < 0
        ) {

            $error = 'Enter a valid price.';

        } else {

            $stmt = $conn->prepare("
                INSERT INTO meals
                (
                    name,
                    description,
                    price,
                    is_available
                )
                VALUES (?, ?, ?, ?)
            ");

            if ($stmt) {

                $price_value = (float)$price;

                $stmt->bind_param(
                    'ssdi',
                    $name,
                    $description,
                    $price_value,
                    $is_available
                );

                if ($stmt->execute()) {

                    header(
                        'Location: index.php?added=1'
                    );

                    exit;

                } else {

                    $error =
                        'Unable to add meal.';
                }

                $stmt->close();
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <h2>Add Meal</h2>

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
                    value="<?php echo e($name); ?>"
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
                ><?php echo e($description); ?></textarea>

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
                    value="<?php echo e($price); ?>"
                    required
                >

            </div>


            <div class="form-check mb-4">

                <input
                    type="checkbox"
                    name="is_available"
                    class="form-check-input"
                    id="available"
                    <?php echo $is_available ? 'checked' : ''; ?>
                >

                <label
                    for="available"
                    class="form-check-label"
                >
                    Available
                </label>

            </div>


            <button class="btn btn-primary">
                Save Meal
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