<?php




require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Place Tiffin Order';
$active_page = 'orders';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$customer_id = (int) $_SESSION['customer_id'];

$meal_id = isset($_GET['meal_id']) ? (int) $_GET['meal_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meal_id = isset($_POST['meal_id']) ? (int) $_POST['meal_id'] : 0;
}

/*
|--------------------------------------------------------------------------
| Fetch selected meal
|--------------------------------------------------------------------------
*/

$meal = null;
$meal_error = '';

if ($meal_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, name, description, price, image, is_available
        FROM meals
        WHERE id = ?
          AND is_available = 1
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $meal_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $meal = $result->fetch_assoc();

        $stmt->close();
    } else {
        $meal_error = 'Unable to load the selected meal.';
    }

    if (!$meal && $meal_error === '') {
        $meal_error = 'The selected meal does not exist or is currently unavailable.';
    }
} else {
    $meal_error = 'Please select a valid meal first.';
}

/*
|--------------------------------------------------------------------------
| Fetch customer addresses
|--------------------------------------------------------------------------
*/

$addresses = [];

$stmt = $conn->prepare("
    SELECT
        id,
        address_label,
        address_line,
        landmark,
        city,
        state,
        postal_code,
        is_default
    FROM addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
");

if ($stmt) {
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }

    $stmt->close();
}

$errors = [];

$quantity = 1;
$delivery_date = '';
$delivery_time = '';
$address_id = 0;
$notes = '';

/*
|--------------------------------------------------------------------------
| Create Order
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $meal) {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 20
            ]
        ]
    );

    if ($quantity === false || $quantity === null) {
        $errors[] = 'Please enter a valid quantity between 1 and 20.';
        $quantity = 1;
    }

    $delivery_date = trim($_POST['delivery_date'] ?? '');
    $delivery_time = trim($_POST['delivery_time'] ?? '');
    $address_id = (int) ($_POST['address_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Delivery date validation
    |--------------------------------------------------------------------------
    */

    if ($delivery_date === '') {
        $errors[] = 'Please select a delivery date.';
    } else {
        $date_object = DateTime::createFromFormat('Y-m-d', $delivery_date);

        if (!$date_object || $date_object->format('Y-m-d') !== $delivery_date) {
            $errors[] = 'Please select a valid delivery date.';
        } elseif ($delivery_date < date('Y-m-d')) {
            $errors[] = 'Delivery date cannot be in the past.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delivery time validation
    |--------------------------------------------------------------------------
    */

    if ($delivery_time === '') {
        $errors[] = 'Please select a delivery time.';
    }

    /*
    |--------------------------------------------------------------------------
    | Address ownership validation
    |--------------------------------------------------------------------------
    */

    $address = null;

    if ($address_id <= 0) {
        $errors[] = 'Please select a delivery address.';
    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                address_line,
                landmark,
                city,
                state,
                postal_code
            FROM addresses
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param(
                'ii',
                $address_id,
                $customer_id
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $address = $result->fetch_assoc();

            $stmt->close();
        }

        if (!$address) {
            $errors[] = 'The selected delivery address is invalid.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Server-side price calculation
    |--------------------------------------------------------------------------
    */

    $unit_price = (float) $meal['price'];
    $subtotal = round($unit_price * $quantity, 2);

    /*
    |--------------------------------------------------------------------------
    | Delivery fee
    |--------------------------------------------------------------------------
    |
    | No delivery_fee rule was provided in the existing schema.
    | Therefore the safe default is 0 rather than inventing a pricing rule.
    |
    */

    $delivery_fee = 0.00;
    $total_amount = round($subtotal + $delivery_fee, 2);

    /*
    |--------------------------------------------------------------------------
    | Create order
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $order_number = '';

        for ($attempt = 0; $attempt < 5; $attempt++) {

            $candidate = 'ST-' .
                date('Ymd') .
                '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $check = $conn->prepare("
                SELECT id
                FROM orders
                WHERE order_number = ?
                LIMIT 1
            ");

            if (!$check) {
                $errors[] = 'Unable to validate order number.';
                break;
            }

            $check->bind_param('s', $candidate);
            $check->execute();

            $result = $check->get_result();
            $exists = $result->num_rows > 0;

            $check->close();

            if (!$exists) {
                $order_number = $candidate;
                break;
            }
        }

        if ($order_number === '') {
            $errors[] = 'Unable to generate a unique order number.';
        }
    }

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | subscription_id
            |--------------------------------------------------------------------------
            |
            | No subscription was selected by this page.
            | NULL is used because the column already supports a subscription
            | relationship without requiring one for every order.
            |
            */

            $subscription_id = null;

            $order_status = 'pending';

            $stmt = $conn->prepare("
                INSERT INTO orders
                (
                    order_number,
                    user_id,
                    subscription_id,
                    address_id,
                    delivery_date,
                    delivery_time,
                    subtotal,
                    delivery_fee,
                    total_amount,
                    order_status,
                    notes,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW(),
                    NOW()
                )
            ");

            if (!$stmt) {
                throw new Exception('Unable to prepare order creation.');
            }

            $stmt->bind_param(
                'siiissdddss',
                $order_number,
                $customer_id,
                $subscription_id,
                $address_id,
                $delivery_date,
                $delivery_time,
                $subtotal,
                $delivery_fee,
                $total_amount,
                $order_status,
                $notes
            );

            if (!$stmt->execute()) {
                throw new Exception('Unable to create the order.');
            }

            $order_id = $conn->insert_id;

            $stmt->close();

            /*
            |--------------------------------------------------------------------------
            | Insert order item
            |--------------------------------------------------------------------------
            */

            $item_subtotal = round($unit_price * $quantity, 2);

            $stmt = $conn->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    meal_id,
                    quantity,
                    unit_price,
                    subtotal,
                    created_at
                )
                VALUES
                (?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception('Unable to prepare order item.');
            }

            $stmt->bind_param(
                'iiidd',
                $order_id,
                $meal_id,
                $quantity,
                $unit_price,
                $item_subtotal
            );

            if (!$stmt->execute()) {
                throw new Exception('Unable to create the order item.');
            }

            $stmt->close();

            $conn->commit();

            $_SESSION['order_success'] = 'Your tiffin order has been placed successfully.';
            $_SESSION['created_order_number'] = $order_number;

            header('Location: index.php');
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $errors[] = 'Unable to create your order. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="container py-4 py-lg-5">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <strong>Please fix the following:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($meal): ?>

        <div class="mb-4">
            <a href="../meals/index.php"
               class="text-decoration-none text-dark">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Meals
            </a>

            <h1 class="fw-bold mt-3 mb-1">
                Place Your Tiffin Order
            </h1>

            <p class="text-muted mb-0">
                Fresh food, prepared with care and delivered to your doorstep.
            </p>
        </div>

        <div class="row g-4">

            <!-- Meal -->
            <div class="col-lg-5">

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">

                    <?php if (!empty($meal['image'])): ?>

                        <img
                            src="<?= htmlspecialchars($meal['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($meal['name'], ENT_QUOTES, 'UTF-8') ?>"
                            class="w-100"
                            style="height:260px;object-fit:cover;"
                        >

                    <?php else: ?>

                        <div class="d-flex align-items-center justify-content-center bg-light"
                             style="height:260px;">
                            <i class="bi bi-egg-fried display-1 text-warning"></i>
                        </div>

                    <?php endif; ?>

                    <div class="card-body p-4">

                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>
                            Available
                        </span>

                        <h2 class="fw-bold mt-3">
                            <?= htmlspecialchars($meal['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>

                        <?php if (!empty($meal['description'])): ?>
                            <p class="text-muted">
                                <?= nl2br(htmlspecialchars($meal['description'], ENT_QUOTES, 'UTF-8')) ?>
                            </p>
                        <?php endif; ?>

                        <div class="fs-3 fw-bold text-warning">
                            ₹<?= number_format((float)$meal['price'], 2) ?>
                        </div>

                        <small class="text-muted">
                            Price per meal
                        </small>

                    </div>
                </div>

            </div>

            <!-- Order form -->
            <div class="col-lg-7">

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-lg-5">

                        <form method="POST" novalidate>

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                            <input type="hidden"
                                   name="meal_id"
                                   value="<?= (int)$meal['id'] ?>">

                            <h5 class="fw-bold mb-4">
                                <i class="bi bi-cart-check text-warning me-2"></i>
                                Order Details
                            </h5>

                            <div class="mb-4">

                                <label class="form-label fw-semibold">
                                    Quantity
                                </label>

                                <input
                                    type="number"
                                    name="quantity"
                                    class="form-control form-control-lg"
                                    min="1"
                                    max="20"
                                    value="<?= (int)$quantity ?>"
                                    required
                                >

                            </div>

                            <div class="row g-3 mb-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Delivery Date
                                    </label>

                                    <input
                                        type="date"
                                        name="delivery_date"
                                        class="form-control"
                                        min="<?= date('Y-m-d') ?>"
                                        value="<?= htmlspecialchars($delivery_date, ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Delivery Time
                                    </label>

                                    <input
                                        type="time"
                                        name="delivery_time"
                                        class="form-control"
                                        value="<?= htmlspecialchars($delivery_time, ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="mb-4">

                                <div class="d-flex justify-content-between align-items-center mb-2">

                                    <label class="form-label fw-semibold mb-0">
                                        Delivery Address
                                    </label>

                                    <a href="../profile.php"
                                       class="small text-warning text-decoration-none">
                                        Manage Address
                                    </a>

                                </div>

                                <?php if (empty($addresses)): ?>

                                    <div class="alert alert-warning border-0">
                                        <i class="bi bi-geo-alt me-2"></i>

                                        You don't have a delivery address yet.
                                        Please add one from your profile before placing an order.
                                    </div>

                                <?php else: ?>

                                    <?php foreach ($addresses as $address): ?>

                                        <label class="border rounded-3 p-3 mb-2 d-block"
                                               style="cursor:pointer;">

                                            <div class="form-check">

                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    name="address_id"
                                                    value="<?= (int)$address['id'] ?>"
                                                    <?= $address_id === (int)$address['id'] ? 'checked' : '' ?>
                                                    required
                                                >

                                                <span class="form-check-label">

                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            $address['address_label'] ?: 'Delivery Address',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </strong>

                                                    <br>

                                                    <small class="text-muted">

                                                        <?= htmlspecialchars($address['address_line'], ENT_QUOTES, 'UTF-8') ?>

                                                        <?php if (!empty($address['landmark'])): ?>
                                                            , <?= htmlspecialchars($address['landmark'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>

                                                        <br>

                                                        <?= htmlspecialchars($address['city'], ENT_QUOTES, 'UTF-8') ?>,
                                                        <?= htmlspecialchars($address['state'], ENT_QUOTES, 'UTF-8') ?>
                                                        -
                                                        <?= htmlspecialchars($address['postal_code'], ENT_QUOTES, 'UTF-8') ?>

                                                    </small>

                                                </span>

                                            </div>

                                        </label>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </div>

                            <div class="mb-4">

                                <label class="form-label fw-semibold">
                                    Notes
                                    <span class="text-muted fw-normal">(Optional)</span>
                                </label>

                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Any special delivery instructions?"
                                ><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>

                            </div>

                            <div class="bg-light rounded-4 p-4 mb-4">

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Meal</span>
                                    <span class="fw-semibold">
                                        <?= htmlspecialchars($meal['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Quantity</span>
                                    <span id="summaryQuantity">
                                        <?= (int)$quantity ?>
                                    </span>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <strong id="summarySubtotal">
                                        ₹<?= number_format($subtotal ?? (float)$meal['price'], 2) ?>
                                    </strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span>Delivery Fee</span>
                                    <span>₹0.00</span>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between fs-5">
                                    <strong>Total</strong>
                                    <strong class="text-warning" id="summaryTotal">
                                        ₹<?= number_format($total_amount ?? (float)$meal['price'], 2) ?>
                                    </strong>
                                </div>

                            </div>

                            <?php if (!empty($addresses)): ?>

                                <button type="submit"
                                        class="btn btn-warning btn-lg w-100 rounded-3 fw-bold">

                                    <i class="bi bi-bag-check me-2"></i>
                                    Confirm Tiffin Order

                                </button>

                            <?php endif; ?>

                        </form>

                    </div>
                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= htmlspecialchars($meal_error, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <a href="../meals/index.php"
           class="btn btn-warning">
            <i class="bi bi-arrow-left me-1"></i>
            Browse Meals
        </a>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const quantity = document.querySelector('[name="quantity"]');
    const summaryQuantity = document.getElementById('summaryQuantity');
    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryTotal = document.getElementById('summaryTotal');

    const unitPrice = <?= json_encode((float)$meal['price']) ?>;

    if (quantity) {
        quantity.addEventListener('input', function () {

            let qty = parseInt(this.value, 10);

            if (!Number.isFinite(qty) || qty < 1) {
                qty = 1;
            }

            if (qty > 20) {
                qty = 20;
            }

            const subtotal = unitPrice * qty;

            summaryQuantity.textContent = qty;

            summarySubtotal.textContent =
                '₹' + subtotal.toFixed(2);

            summaryTotal.textContent =
                '₹' + subtotal.toFixed(2);
        });
    }

});
</script>

<?php
require_once __DIR__ . '/../includes/customer_footer.php';
?>