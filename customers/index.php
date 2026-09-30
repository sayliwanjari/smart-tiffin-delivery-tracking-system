<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/../config/database.php';


$page_title = 'Dashboard';
$active_page = 'home';

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$total_orders = 0;
$recent_orders = [];
$subscription = null;
$upcoming_delivery = null;


/*
|--------------------------------------------------------------------------
| Total orders
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE user_id = ?
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $customer_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result) {

            $row = $result->fetch_assoc();

            $total_orders =
                (int) ($row['total'] ?? 0);
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Current subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.start_date,
        s.end_date,
        s.status,
        s.meals_remaining,
        p.name AS plan_name,
        p.price AS plan_price,
        p.total_meals
    FROM subscriptions AS s

    INNER JOIN subscription_plans AS p
        ON s.plan_id = p.id

    WHERE s.user_id = ?

    AND s.status IN ('active', 'paused')

    ORDER BY s.created_at DESC

    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $customer_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result) {
            $subscription = $result->fetch_assoc();
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Recent orders
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.total_amount,
        o.order_status,
        o.created_at,

        GROUP_CONCAT(
            DISTINCT m.name
            ORDER BY m.name
            SEPARATOR ', '
        ) AS meal_names

    FROM orders AS o

    LEFT JOIN order_items AS oi
        ON o.id = oi.order_id

    LEFT JOIN meals AS m
        ON oi.meal_id = m.id

    WHERE o.user_id = ?

    GROUP BY
        o.id,
        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.total_amount,
        o.order_status,
        o.created_at

    ORDER BY o.created_at DESC

    LIMIT 5
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $customer_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result) {

            while ($row = $result->fetch_assoc()) {

                $recent_orders[] = $row;
            }
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Upcoming delivery
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.order_number,
        o.delivery_date,
        o.delivery_time,
        o.order_status,
        o.total_amount,
        a.address_line,
        a.city

    FROM orders AS o

    INNER JOIN addresses AS a
        ON o.address_id = a.id

    WHERE o.user_id = ?

    AND o.delivery_date >= CURDATE()

    AND o.order_status IN ('pending', 'confirmed')

    ORDER BY
        o.delivery_date ASC,
        o.delivery_time ASC

    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $customer_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result) {
            $upcoming_delivery =
                $result->fetch_assoc();
        }
    }

    $stmt->close();
}


require_once __DIR__ . '/includes/customer_header.php';

?>


<div class="container py-4 py-lg-5">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="customer-hero mb-4 mb-lg-5">

        <div class="row align-items-center">

            <div class="col-lg-8 position-relative">

                <span class="hero-badge mb-3">
                    <i class="bi bi-stars me-1"></i>
                    Smart meals for your everyday
                </span>

                <h1 class="mt-3 mb-3">
                    Homemade happiness,
                    <span style="color:#ff6b00;">
                        delivered.
                    </span>
                </h1>

                <p class="lead text-muted mb-4">
                    Good food shouldn't be complicated.
                    Enjoy fresh, reliable tiffin meals
                    delivered right to your doorstep.
                </p>

                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="meals/"
                        class="btn btn-customer-primary px-4 py-3"
                    >
                        <i class="bi bi-egg-fried me-2"></i>
                        Explore Meals
                    </a>

                    <a
                        href="plans/"
                        class="btn btn-outline-dark px-4 py-3"
                    >
                        View Tiffin Plans
                        <i class="bi bi-arrow-right ms-2"></i>
                    </a>

                </div>

            </div>


            <div class="col-lg-4 d-none d-lg-block text-center">

                <div
                    style="
                        font-size:140px;
                        line-height:1;
                    "
                >
                    🍱
                </div>

                <div class="fw-bold mt-3">
                    Fresh • Homemade • Reliable
                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="row g-3 mb-4 mb-lg-5">

        <div class="col-6 col-lg">

            <a
                href="meals/"
                class="text-decoration-none text-dark"
            >

                <div class="quick-card">

                    <div class="quick-icon mb-3">
                        <i class="bi bi-egg-fried"></i>
                    </div>

                    <h6 class="fw-bold">
                        Order Tiffin
                    </h6>

                    <small class="text-muted">
                        Choose your meal
                    </small>

                </div>

            </a>

        </div>


        <div class="col-6 col-lg">

            <a
                href="meals/"
                class="text-decoration-none text-dark"
            >

                <div class="quick-card">

                    <div class="quick-icon mb-3">
                        <i class="bi bi-grid"></i>
                    </div>

                    <h6 class="fw-bold">
                        Browse Meals
                    </h6>

                    <small class="text-muted">
                        See today's menu
                    </small>

                </div>

            </a>

        </div>


        <div class="col-6 col-lg">

            <a
                href="plans/"
                class="text-decoration-none text-dark"
            >

                <div class="quick-card">

                    <div class="quick-icon mb-3">
                        <i class="bi bi-calendar-check"></i>
                    </div>

                    <h6 class="fw-bold">
                        My Plan
                    </h6>

                    <small class="text-muted">
                        Manage subscription
                    </small>

                </div>

            </a>

        </div>


        <div class="col-6 col-lg">

            <a
                href="orders/"
                class="text-decoration-none text-dark"
            >

                <div class="quick-card">

                    <div class="quick-icon mb-3">
                        <i class="bi bi-receipt"></i>
                    </div>

                    <h6 class="fw-bold">
                        My Orders
                    </h6>

                    <small class="text-muted">
                        <?php echo $total_orders; ?> orders
                    </small>

                </div>

            </a>

        </div>


        <div class="col-12 col-lg">

            <a
                href="tracking.php"
                class="text-decoration-none text-dark"
            >

                <div class="quick-card">

                    <div class="quick-icon mb-3">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <h6 class="fw-bold">
                        Track Delivery
                    </h6>

                    <small class="text-muted">
                        Follow your tiffin
                    </small>

                </div>

            </a>

        </div>

    </div>


    <!-- =====================================================
         SUBSCRIPTION + UPCOMING DELIVERY
    ====================================================== -->

    <div class="row g-4 mb-4 mb-lg-5">


        <!-- SUBSCRIPTION -->

        <div class="col-lg-6">

            <div class="dashboard-card">

                <div class="dashboard-card-header d-flex justify-content-between">

                    <div>

                        <h5 class="fw-bold mb-1">
                            My Subscription
                        </h5>

                        <small class="text-muted">
                            Your current tiffin plan
                        </small>

                    </div>

                    <i class="bi bi-calendar-heart fs-3 text-warning"></i>

                </div>


                <div class="dashboard-card-body">

                    <?php if ($subscription): ?>

                        <div class="d-flex justify-content-between align-items-start mb-3">

                            <div>

                                <h4 class="fw-bold mb-1">
                                    <?php
                                    echo e(
                                        $subscription['plan_name']
                                    );
                                    ?>
                                </h4>

                                <small class="text-muted">
                                    <?php
                                    echo e(
                                        $subscription['start_date']
                                    );
                                    ?>
                                    —
                                    <?php
                                    echo e(
                                        $subscription['end_date']
                                    );
                                    ?>
                                </small>

                            </div>

                            <span class="badge bg-success">
                                <?php
                                echo e(
                                    ucfirst(
                                        $subscription['status']
                                    )
                                );
                                ?>
                            </span>

                        </div>


                        <div class="row g-3">

                            <div class="col-6">

                                <div class="p-3 bg-light rounded-3">

                                    <small class="text-muted">
                                        Meals remaining
                                    </small>

                                    <h4 class="fw-bold mb-0 mt-1">
                                        <?php
                                        echo (int)
                                            $subscription['meals_remaining'];
                                        ?>
                                    </h4>

                                </div>

                            </div>


                            <div class="col-6">

                                <div class="p-3 bg-light rounded-3">

                                    <small class="text-muted">
                                        Plan price
                                    </small>

                                    <h4 class="fw-bold mb-0 mt-1">
                                        ₹<?php
                                        echo number_format(
                                            (float)
                                            $subscription['plan_price'],
                                            2
                                        );
                                        ?>
                                    </h4>

                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-4">

                            <i class="bi bi-calendar-plus fs-1 text-muted"></i>

                            <h6 class="fw-bold mt-3">
                                No active subscription
                            </h6>

                            <p class="text-muted small">
                                Choose a plan and make everyday meals easier.
                            </p>

                            <a
                                href="plans/"
                                class="btn btn-customer-primary"
                            >
                                Explore Plans
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- UPCOMING DELIVERY -->

        <div class="col-lg-6">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h5 class="fw-bold mb-1">
                        Upcoming Delivery
                    </h5>

                    <small class="text-muted">
                        Your next tiffin
                    </small>

                </div>


                <div class="dashboard-card-body">

                    <?php if ($upcoming_delivery): ?>

                        <div class="d-flex align-items-center gap-3 mb-4">

                            <div
                                class="quick-icon"
                                style="flex-shrink:0;"
                            >
                                <i class="bi bi-truck"></i>
                            </div>

                            <div>

                                <h5 class="fw-bold mb-1">
                                    <?php
                                    echo e(
                                        $upcoming_delivery['order_number']
                                    );
                                    ?>
                                </h5>

                                <small class="text-muted">
                                    <?php
                                    echo e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $upcoming_delivery['delivery_date']
                                            )
                                        )
                                    );
                                    ?>

                                    at

                                    <?php
                                    echo e(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $upcoming_delivery['delivery_time']
                                            )
                                        )
                                    );
                                    ?>
                                </small>

                            </div>

                        </div>


                        <div class="mb-3">

                            <span class="badge bg-warning-subtle text-warning-emphasis">
                                <?php
                                echo e(
                                    ucfirst(
                                        $upcoming_delivery['order_status']
                                    )
                                );
                                ?>
                            </span>

                        </div>


                        <div class="p-3 bg-light rounded-3">

                            <small class="text-muted">
                                Delivery address
                            </small>

                            <div class="fw-semibold mt-1">
                                <?php
                                echo e(
                                    $upcoming_delivery['address_line']
                                );
                                ?>
                            </div>

                            <small class="text-muted">
                                <?php
                                echo e(
                                    $upcoming_delivery['city']
                                );
                                ?>
                            </small>

                        </div>


                    <?php else: ?>

                        <div class="text-center py-4">

                            <i class="bi bi-truck fs-1 text-muted"></i>

                            <h6 class="fw-bold mt-3">
                                No upcoming delivery
                            </h6>

                            <p class="text-muted small">
                                Your next tiffin delivery will appear here.
                            </p>

                            <a
                                href="meals/"
                                class="btn btn-customer-primary"
                            >
                                Order a Tiffin
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         RECENT ORDERS
    ====================================================== -->

    <div class="dashboard-card mb-5">

        <div class="dashboard-card-header d-flex justify-content-between align-items-center">

            <div>

                <h5 class="fw-bold mb-1">
                    Recent Orders
                </h5>

                <small class="text-muted">
                    Your latest Smart Tiffin orders
                </small>

            </div>

            <a
                href="orders/"
                class="btn btn-sm btn-outline-dark"
            >
                View All
            </a>

        </div>


        <div class="dashboard-card-body p-0">

            <?php if (!empty($recent_orders)): ?>

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="px-4">
                                    Order
                                </th>

                                <th>
                                    Meal
                                </th>

                                <th>
                                    Delivery
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($recent_orders as $order): ?>

                            <tr>

                                <td class="px-4">

                                    <strong>
                                        <?php
                                        echo e(
                                            $order['order_number']
                                        );
                                        ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?php
                                        echo e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            )
                                        );
                                        ?>
                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo e(
                                        $order['meal_names']
                                        ?: 'Tiffin'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $order['delivery_date']
                                            )
                                        )
                                    );
                                    ?>

                                </td>


                                <td class="fw-bold">

                                    ₹<?php
                                    echo number_format(
                                        (float)
                                        $order['total_amount'],
                                        2
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php

                                    $status_class = match (
                                        $order['order_status']
                                    ) {

                                        'completed' =>
                                            'bg-success-subtle text-success',

                                        'confirmed' =>
                                            'bg-primary-subtle text-primary',

                                        'cancelled' =>
                                            'bg-danger-subtle text-danger',

                                        default =>
                                            'bg-warning-subtle text-warning-emphasis'
                                    };

                                    ?>

                                    <span
                                        class="status-badge <?php echo $status_class; ?>"
                                    >
                                        <?php
                                        echo e(
                                            ucfirst(
                                                $order['order_status']
                                            )
                                        );
                                        ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="text-center py-5">

                    <div class="quick-icon mx-auto mb-3">
                        <i class="bi bi-receipt"></i>
                    </div>

                    <h6 class="fw-bold">
                        No orders yet
                    </h6>

                    <p class="text-muted small">
                        Your Smart Tiffin journey starts with a delicious meal.
                    </p>

                    <a
                        href="meals/"
                        class="btn btn-customer-primary"
                    >
                        Explore Meals
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         PROMOTIONAL SECTION
    ====================================================== -->

    <section
        class="p-4 p-lg-5 rounded-4 text-white"
        style="
            background:
            linear-gradient(
                135deg,
                #ff6b00,
                #f59e0b
            );
        "
    >

        <div class="row align-items-center">

            <div class="col-lg-8">

                <h3 class="fw-bold">
                    Fresh meals. Delivered daily.
                </h3>

                <p class="mb-0 opacity-75">
                    Simple ordering, reliable delivery and
                    homemade food whenever you need it.
                </p>

            </div>

            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                <a
                    href="plans/"
                    class="btn btn-light px-4"
                >
                    Find Your Plan
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>

            </div>

        </div>

    </section>

</div>


<?php

require_once __DIR__ . '/includes/customer_footer.php';

?>