<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Add Order';
$active_page = 'orders';

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error_message = '';

$customers = [];
$meals = [];
$addresses = [];

$selected_customer = '';
$selected_address = '';
$delivery_date = '';
$delivery_time = '';
$notes = '';

$selected_meals = [];
$quantities = [];


/*
|--------------------------------------------------------------------------
| Load customers
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone
    FROM users
    WHERE status = 'active'
    ORDER BY name ASC
");

if (!$stmt) {

    $error_message = 'Unable to load customers.';

} elseif (!$stmt->execute()) {

    $error_message = 'Unable to load customers.';

} else {

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Load available meals
|--------------------------------------------------------------------------
*/

if ($error_message === '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            price,
            is_available
        FROM meals
        WHERE is_available = 1
        ORDER BY name ASC
    ");

    if (!$stmt) {

        $error_message = 'Unable to load meals.';

    } elseif (!$stmt->execute()) {

        $error_message = 'Unable to load meals.';

    } else {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $meals[] = $row;
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Load customer addresses
|--------------------------------------------------------------------------
*/

if ($error_message === '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            user_id,
            address_label,
            address_line,
            landmark,
            city,
            state,
            postal_code,
            is_default
        FROM addresses
        ORDER BY
            user_id ASC,
            is_default DESC,
            id DESC
    ");

    if (!$stmt) {

        $error_message = 'Unable to load customer addresses.';

    } elseif (!$stmt->execute()) {

        $error_message = 'Unable to load customer addresses.';

    } else {

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Form submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selected_customer = filter_var(
        $_POST['customer_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $selected_address = filter_var(
        $_POST['address_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $delivery_date = trim(
        $_POST['delivery_date'] ?? ''
    );

    $delivery_time = trim(
        $_POST['delivery_time'] ?? ''
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );

    $selected_meals = $_POST['meal_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];


    /*
     * CSRF
     */
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $error_message = 'Invalid request. Please try again.';

    }


    /*
     * Customer validation
     */
    elseif (
        !$selected_customer ||
        $selected_customer < 1
    ) {

        $error_message = 'Please select a customer.';

    }


    /*
     * Address validation
     */
    elseif (
        !$selected_address ||
        $selected_address < 1
    ) {

        $error_message = 'Please select a delivery address.';

    }


    /*
     * Delivery date
     */
    elseif ($delivery_date === '') {

        $error_message = 'Please select a delivery date.';

    }


    /*
     * Delivery time
     */
    elseif ($delivery_time === '') {

        $error_message = 'Please select a delivery time.';

    }


    /*
     * Meal validation
     */
    elseif (
        !is_array($selected_meals) ||
        count($selected_meals) === 0
    ) {

        $error_message = 'Please select at least one meal.';

    }


    /*
     * Validate date/time format
     */
    if ($error_message === '') {

        $date_object = DateTime::createFromFormat(
            'Y-m-d',
            $delivery_date
        );

        $date_valid =
            $date_object &&
            $date_object->format('Y-m-d') === $delivery_date;

        $time_object = DateTime::createFromFormat(
            'H:i',
            $delivery_time
        );

        $time_valid =
            $time_object &&
            $time_object->format('H:i') === $delivery_time;

        if (!$date_valid) {

            $error_message = 'Invalid delivery date.';

        } elseif (!$time_valid) {

            $error_message = 'Invalid delivery time.';

        }
    }


    /*
     * Validate customer exists.
     */
    if ($error_message === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND status = 'active'
            LIMIT 1
        ");

        if (!$stmt) {

            $error_message = 'Unable to validate customer.';

        } else {

            $stmt->bind_param(
                'i',
                $selected_customer
            );

            if (!$stmt->execute()) {

                $error_message = 'Unable to validate customer.';

            } else {

                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $error_message = 'Selected customer is invalid.';
                }
            }

            $stmt->close();
        }
    }


    /*
     * Validate address belongs to selected customer.
     */
    if ($error_message === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM addresses
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error_message = 'Unable to validate address.';

        } else {

            $stmt->bind_param(
                'ii',
                $selected_address,
                $selected_customer
            );

            if (!$stmt->execute()) {

                $error_message = 'Unable to validate address.';

            } else {

                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $error_message =
                        'Selected address does not belong to the selected customer.';
                }
            }

            $stmt->close();
        }
    }


    /*
     * Validate meals and quantities.
     */
    $order_items = [];
    $subtotal = 0.00;

    if ($error_message === '') {

        $unique_meal_ids = [];

        foreach ($selected_meals as $meal_id_raw) {

            $meal_id = filter_var(
                $meal_id_raw,
                FILTER_VALIDATE_INT
            );

            if (!$meal_id || $meal_id < 1) {

                $error_message = 'Invalid meal selected.';
                break;
            }

            if (isset($unique_meal_ids[$meal_id])) {

                $error_message =
                    'A meal cannot be selected more than once.';
                break;
            }

            $unique_meal_ids[$meal_id] = true;

            $quantity = filter_var(
                $quantities[$meal_id] ?? '',
                FILTER_VALIDATE_INT
            );

            if (
                !$quantity ||
                $quantity < 1 ||
                $quantity > 100
            ) {

                $error_message =
                    'Meal quantity must be between 1 and 100.';
                break;
            }

            $order_items[] = [
                'meal_id' => $meal_id,
                'quantity' => $quantity
            ];
        }
    }


    /*
     * Fetch actual meal prices from database.
     *
     * Never trust prices submitted by browser.
     */
    if ($error_message === '') {

        foreach ($order_items as &$item) {

            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    price,
                    is_available
                FROM meals
                WHERE id = ?
                AND is_available = 1
                LIMIT 1
            ");

            if (!$stmt) {

                $error_message =
                    'Unable to validate selected meal.';
                break;
            }

            $stmt->bind_param(
                'i',
                $item['meal_id']
            );

            if (!$stmt->execute()) {

                $error_message =
                    'Unable to validate selected meal.';

                $stmt->close();
                break;
            }

            $result = $stmt->get_result();

            $meal = $result->fetch_assoc();

            $stmt->close();

            if (!$meal) {

                $error_message =
                    'One of the selected meals is unavailable.';
                break;
            }

            $item['name'] = $meal['name'];
            $item['unit_price'] = (float) $meal['price'];
            $item['subtotal'] =
                $item['unit_price'] * $item['quantity'];

            $subtotal += $item['subtotal'];
        }

        unset($item);
    }


    /*
     * Create order.
     */
    if ($error_message === '') {

        /*
         * Delivery fee is zero because the current schema
         * has delivery_fee but no separate admin fee input.
         */
        $delivery_fee = 0.00;

        $total_amount =
            $subtotal + $delivery_fee;


        /*
         * Generate unique order number.
         */
        $order_number = '';

        for ($attempt = 0; $attempt < 5; $attempt++) {

            $order_number =
                'ORD' .
                date('YmdHis') .
                strtoupper(
                    bin2hex(random_bytes(2))
                );

            $check_stmt = $conn->prepare("
                SELECT id
                FROM orders
                WHERE order_number = ?
                LIMIT 1
            ");

            if (!$check_stmt) {
                break;
            }

            $check_stmt->bind_param(
                's',
                $order_number
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            $exists =
                $check_result->num_rows > 0;

            $check_stmt->close();

            if (!$exists) {
                break;
            }

            $order_number = '';
        }


        if ($order_number === '') {

            $error_message =
                'Unable to generate a unique order number.';

        }
    }


    /*
     * Transaction
     */
    if ($error_message === '') {

        $conn->begin_transaction();

        try {

            /*
             * Orders table requires:
             * order_number
             * user_id
             * address_id
             * delivery_date
             * delivery_time
             * subtotal
             * delivery_fee
             * total_amount
             *
             * subscription_id is nullable.
             *
             * order_status defaults to pending.
             */
            $stmt = $conn->prepare("
                INSERT INTO orders
                (
                    order_number,
                    user_id,
                    address_id,
                    delivery_date,
                    delivery_time,
                    subtotal,
                    delivery_fee,
                    total_amount,
                    order_status,
                    notes
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    'Unable to prepare order creation.'
                );
            }

            $stmt->bind_param(
                'siissddds',
                $order_number,
                $selected_customer,
                $selected_address,
                $delivery_date,
                $delivery_time,
                $subtotal,
                $delivery_fee,
                $total_amount,
                $notes
            );

            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    'Unable to create order.'
                );
            }

            $order_id = $conn->insert_id;

            $stmt->close();


            /*
             * Insert order items.
             */
            $item_stmt = $conn->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    meal_id,
                    quantity,
                    unit_price,
                    subtotal
                )
                VALUES
                (?, ?, ?, ?, ?)
            ");

            if (!$item_stmt) {

                throw new Exception(
                    'Unable to prepare order items.'
                );
            }


            foreach ($order_items as $item) {

                $meal_id = $item['meal_id'];
                $quantity = $item['quantity'];
                $unit_price = $item['unit_price'];
                $item_subtotal = $item['subtotal'];

                $item_stmt->bind_param(
                    'iiidd',
                    $order_id,
                    $meal_id,
                    $quantity,
                    $unit_price,
                    $item_subtotal
                );

                if (!$item_stmt->execute()) {

                    $item_stmt->close();

                    throw new Exception(
                        'Unable to create order items.'
                    );
                }
            }

            $item_stmt->close();


            /*
             * Commit.
             */
            $conn->commit();

            $_SESSION['csrf_token'] =
                bin2hex(random_bytes(32));

            header(
                'Location: index.php?added=1'
            );

            exit;

        } catch (Throwable $exception) {

            $conn->rollback();

            $error_message =
                'Unable to create the order. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h2 class="fw-bold mb-1">
                Add Order
            </h2>

            <p class="text-muted mb-0">
                Create a new customer tiffin order.
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left me-2"></i>
            Back to Orders
        </a>

    </div>

</div>


<?php if ($error_message !== ''): ?>

    <div
        class="alert alert-danger d-flex align-items-center"
        role="alert"
    >

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        <span>
            <?php echo e($error_message); ?>
        </span>

    </div>

<?php endif; ?>


<?php if (empty($customers)): ?>

    <div class="alert alert-warning">
        <i class="bi bi-person-x me-2"></i>
        No active customers are available. Add a customer first.
    </div>

<?php elseif (empty($meals)): ?>

    <div class="alert alert-warning">
        <i class="bi bi-egg-fried me-2"></i>
        No available meals are currently available.
    </div>

<?php elseif (empty($addresses)): ?>

    <div class="alert alert-warning">
        <i class="bi bi-geo-alt me-2"></i>
        No customer delivery addresses exist. Add a delivery address
        for a customer before creating an order.
    </div>

<?php endif; ?>


<?php if (!empty($customers) && !empty($meals) && !empty($addresses)): ?>

<div class="admin-data-card">

    <div class="admin-data-card-header">

        <h5 class="mb-1">
            Order Information
        </h5>

        <small class="text-muted">
            Select the customer, delivery address and meals.
        </small>

    </div>


    <div class="card-body p-4">

        <form
            method="POST"
            id="addOrderForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrf_token); ?>"
            >


            <!-- Customer -->

            <div class="mb-4">

                <label
                    for="customer_id"
                    class="form-label fw-semibold"
                >
                    Customer <span class="text-danger">*</span>
                </label>

                <select
                    name="customer_id"
                    id="customer_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Customer
                    </option>

                    <?php foreach ($customers as $customer): ?>

                        <option
                            value="<?php echo (int) $customer['id']; ?>"
                            <?php
                            echo (
                                (int) $selected_customer ===
                                (int) $customer['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >
                            <?php
                            echo e(
                                $customer['name'] .
                                ' — ' .
                                $customer['phone']
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Address -->

            <div class="mb-4">

                <label
                    for="address_id"
                    class="form-label fw-semibold"
                >
                    Delivery Address <span class="text-danger">*</span>
                </label>

                <select
                    name="address_id"
                    id="address_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select customer first
                    </option>

                    <?php foreach ($addresses as $address): ?>

                        <option
                            value="<?php echo (int) $address['id']; ?>"
                            data-user-id="<?php echo (int) $address['user_id']; ?>"
                            <?php
                            echo (
                                (int) $selected_address ===
                                (int) $address['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo e(
                                $address['address_label'] .
                                ' — ' .
                                $address['address_line'] .
                                ', ' .
                                $address['city'] .
                                ', ' .
                                $address['state'] .
                                ' - ' .
                                $address['postal_code']
                            );
                            ?>

                            <?php if ((int) $address['is_default'] === 1): ?>
                                — Default
                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <small class="text-muted">
                    Only addresses belonging to the selected customer
                    will be available.
                </small>

            </div>


            <!-- Delivery date/time -->

            <div class="row g-3 mb-4">

                <div class="col-12 col-md-6">

                    <label
                        for="delivery_date"
                        class="form-label fw-semibold"
                    >
                        Delivery Date <span class="text-danger">*</span>
                    </label>

                    <input
                        type="date"
                        name="delivery_date"
                        id="delivery_date"
                        class="form-control"
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo e($delivery_date); ?>"
                        required
                    >

                </div>


                <div class="col-12 col-md-6">

                    <label
                        for="delivery_time"
                        class="form-label fw-semibold"
                    >
                        Delivery Time <span class="text-danger">*</span>
                    </label>

                    <input
                        type="time"
                        name="delivery_time"
                        id="delivery_time"
                        class="form-control"
                        value="<?php echo e($delivery_time); ?>"
                        required
                    >

                </div>

            </div>


            <!-- Meals -->

            <div class="mb-4">

                <div class="d-flex justify-content-between align-items-center mb-2">

                    <label class="form-label fw-semibold mb-0">
                        Meals <span class="text-danger">*</span>
                    </label>

                    <span class="small text-muted">
                        Select meal and quantity
                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th width="50">
                                    Select
                                </th>

                                <th>
                                    Meal
                                </th>

                                <th>
                                    Price
                                </th>

                                <th width="150">
                                    Quantity
                                </th>

                                <th class="text-end">
                                    Subtotal
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($meals as $meal): ?>

                                <?php
                                $meal_id = (int) $meal['id'];
                                $is_selected =
                                    in_array(
                                        $meal_id,
                                        array_map(
                                            'intval',
                                            (array) $selected_meals
                                        ),
                                        true
                                    );

                                $quantity_value =
                                    (int) (
                                        $quantities[$meal_id] ?? 1
                                    );

                                if ($quantity_value < 1) {
                                    $quantity_value = 1;
                                }
                                ?>

                                <tr>

                                    <td>

                                        <input
                                            type="checkbox"
                                            class="form-check-input meal-check"
                                            name="meal_id[]"
                                            value="<?php echo $meal_id; ?>"
                                            data-price="<?php echo e($meal['price']); ?>"
                                            <?php echo $is_selected ? 'checked' : ''; ?>
                                        >

                                    </td>

                                    <td>

                                        <span class="fw-semibold">
                                            <?php echo e($meal['name']); ?>
                                        </span>

                                    </td>

                                    <td>

                                        ₹<?php
                                        echo number_format(
                                            (float) $meal['price'],
                                            2
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            class="form-control meal-quantity"
                                            name="quantity[<?php echo $meal_id; ?>]"
                                            value="<?php echo $quantity_value; ?>"
                                            min="1"
                                            max="100"
                                            data-price="<?php echo e($meal['price']); ?>"
                                            <?php
                                            echo !$is_selected
                                                ? 'disabled'
                                                : '';
                                            ?>
                                        >

                                    </td>

                                    <td class="text-end">

                                        <span class="meal-subtotal">
                                            ₹0.00
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- Total -->

            <div class="row justify-content-end mb-4">

                <div class="col-12 col-md-5">

                    <div class="card border-0 bg-light">

                        <div class="card-body">

                            <div class="d-flex justify-content-between mb-2">

                                <span class="text-muted">
                                    Subtotal
                                </span>

                                <strong id="orderSubtotal">
                                    ₹0.00
                                </strong>

                            </div>

                            <div class="d-flex justify-content-between mb-2">

                                <span class="text-muted">
                                    Delivery Fee
                                </span>

                                <strong>
                                    ₹0.00
                                </strong>

                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">

                                <span class="fw-bold">
                                    Total
                                </span>

                                <strong
                                    class="fs-5"
                                    id="orderTotal"
                                >
                                    ₹0.00
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Notes -->

            <div class="mb-4">

                <label
                    for="notes"
                    class="form-label fw-semibold"
                >
                    Notes
                </label>

                <textarea
                    name="notes"
                    id="notes"
                    class="form-control"
                    rows="3"
                    maxlength="500"
                    placeholder="Optional order notes"
                ><?php echo e($notes); ?></textarea>

            </div>


            <!-- Buttons -->

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">

                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="bi bi-cart-plus me-2"></i>
                    Create Order
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const customerSelect =
        document.getElementById('customer_id');

    const addressSelect =
        document.getElementById('address_id');

    const addressOptions =
        Array.from(
            addressSelect
                ? addressSelect.querySelectorAll('option[data-user-id]')
                : []
        );

    function filterAddresses() {

        if (!customerSelect || !addressSelect) {
            return;
        }

        const customerId =
            customerSelect.value;

        addressSelect.value = '';

        addressOptions.forEach(function (option) {

            const belongsToCustomer =
                option.dataset.userId === customerId;

            option.hidden =
                !belongsToCustomer;

            option.disabled =
                !belongsToCustomer;

        });

        const firstVisible =
            addressOptions.find(function (option) {
                return !option.hidden;
            });

        if (firstVisible) {

            firstVisible.selected = true;

        } else {

            addressSelect.value = '';

        }
    }


    if (customerSelect) {

        customerSelect.addEventListener(
            'change',
            filterAddresses
        );

        filterAddresses();
    }


    /*
     * Meal calculation.
     */
    const mealChecks =
        document.querySelectorAll('.meal-check');

    function calculateTotal() {

        let total = 0;

        mealChecks.forEach(function (checkbox) {

            const row =
                checkbox.closest('tr');

            const quantityInput =
                row.querySelector('.meal-quantity');

            const subtotalElement =
                row.querySelector('.meal-subtotal');

            const price =
                parseFloat(
                    checkbox.dataset.price
                ) || 0;

            let quantity =
                parseInt(
                    quantityInput.value,
                    10
                ) || 0;

            if (!checkbox.checked) {

                quantity = 0;

                quantityInput.disabled = true;

                subtotalElement.textContent =
                    '₹0.00';

            } else {

                quantityInput.disabled = false;

                if (quantity < 1) {
                    quantity = 1;
                    quantityInput.value = 1;
                }

                const subtotal =
                    price * quantity;

                total += subtotal;

                subtotalElement.textContent =
                    '₹' + subtotal.toFixed(2);
            }
        });


        const subtotalElement =
            document.getElementById(
                'orderSubtotal'
            );

        const totalElement =
            document.getElementById(
                'orderTotal'
            );

        if (subtotalElement) {

            subtotalElement.textContent =
                '₹' + total.toFixed(2);
        }

        if (totalElement) {

            totalElement.textContent =
                '₹' + total.toFixed(2);
        }
    }


    mealChecks.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            calculateTotal
        );

        const row =
            checkbox.closest('tr');

        const quantityInput =
            row.querySelector('.meal-quantity');

        quantityInput.addEventListener(
            'input',
            calculateTotal
        );
    });


    calculateTotal();

});
</script>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>