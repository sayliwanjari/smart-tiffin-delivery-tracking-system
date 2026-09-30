<?php
/**
 * Smart Tiffin Delivery & Tracking System
 * Admin Dashboard
 *
 * MODULE 2 - STEP 3
 *
 * This page:
 * - Uses existing Module 1 session/authentication
 * - Uses existing database connection
 * - Displays dashboard summary statistics
 * - Displays recent orders
 * - Displays delivery status
 *
 * No customer/dabbawala management functionality is implemented here.
 */


// ==========================================================
// 1. EXISTING SESSION / AUTHENTICATION
// ==========================================================
//
// Module 1 already contains the authentication/session system.
// We include the existing session configuration instead of
// creating another authentication system.
//

require_once __DIR__ . '/../config/session.php';


// ==========================================================
// 2. EXISTING DATABASE CONNECTION
// ==========================================================
//
// We use the existing database.php file.
// No new database connection is created here.
//

require_once __DIR__ . '/../config/database.php';


// ==========================================================
// 3. PAGE SETTINGS
// ==========================================================

$page_title = 'Admin Dashboard';

$active_page = 'dashboard';


// ==========================================================
// 4. DATABASE CONNECTION COMPATIBILITY
// ==========================================================
//
// The existing project may expose the connection as $conn
// or $pdo.
//
// We do NOT create another database connection.
//
// If your existing database.php uses $conn, it will use $conn.
// If it uses $pdo, it will use $pdo.
//

$database_type = null;

if (isset($conn) && $conn instanceof mysqli) {

    $database_type = 'mysqli';

} elseif (isset($pdo) && $pdo instanceof PDO) {

    $database_type = 'pdo';

}


// ==========================================================
// 5. DEFAULT DASHBOARD VALUES
// ==========================================================
//
// These defaults prevent undefined-variable errors if the
// database is empty.
//

$total_customers = 0;

$total_dabbawalas = 0;

$total_orders = 0;

$pending_deliveries = 0;

$recent_orders = [];

$delivery_statuses = [];


// ==========================================================
// 6. DATABASE QUERIES
// ==========================================================

if ($database_type === 'mysqli') {


    // ------------------------------------------------------
    // Total Customers
    // ------------------------------------------------------

    $query = "
        SELECT COUNT(*) AS total
        FROM users
    ";

    $result = $conn->query($query);

    if ($result) {

        $row = $result->fetch_assoc();

        $total_customers = (int) $row['total'];

        $result->free();

    }


    // ------------------------------------------------------
    // Total Dabbawalas
    // ------------------------------------------------------

    $query = "
        SELECT COUNT(*) AS total
        FROM dabbawalas
    ";

    $result = $conn->query($query);

    if ($result) {

        $row = $result->fetch_assoc();

        $total_dabbawalas = (int) $row['total'];

        $result->free();

    }


    // ------------------------------------------------------
    // Total Orders
    // ------------------------------------------------------

    $query = "
        SELECT COUNT(*) AS total
        FROM orders
    ";

    $result = $conn->query($query);

    if ($result) {

        $row = $result->fetch_assoc();

        $total_orders = (int) $row['total'];

        $result->free();

    }


    // ------------------------------------------------------
    // Pending Deliveries
    // ------------------------------------------------------
    //
    // An assignment is considered pending when it is:
    //
    // assigned
    // accepted
    //
    // We are NOT modifying delivery assignments here.
    // We are only reading existing data.
    //

    $query = "
        SELECT COUNT(*) AS total
        FROM delivery_assignments
        WHERE assignment_status IN ('assigned', 'accepted')
    ";

    $result = $conn->query($query);

    if ($result) {

        $row = $result->fetch_assoc();

        $pending_deliveries = (int) $row['total'];

        $result->free();

    }


    // ======================================================
    // RECENT ORDERS
    // ======================================================
    //
    // Existing orders table:
    //
    // orders.user_id -> users.id
    //
    // We display only the latest 5 orders.
    //

    $query = "
        SELECT
            o.id,
            o.order_number,
            o.total_amount,
            o.order_status,
            o.created_at,
            u.name AS customer_name

        FROM orders AS o

        INNER JOIN users AS u
            ON o.user_id = u.id

        ORDER BY o.created_at DESC

        LIMIT 5
    ";


    $result = $conn->query($query);

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $recent_orders[] = $row;

        }

        $result->free();

    }


    // ======================================================
    // DELIVERY STATUS
    // ======================================================
    //
    // delivery_tracking contains the status history.
    //
    // We retrieve the latest tracking status for each
    // delivery assignment.
    //

    $query = "
        SELECT
            da.id AS assignment_id,
            o.order_number,
            u.name AS customer_name,
            d.name AS dabbawala_name,
            dt.status AS delivery_status,
            dt.updated_at

        FROM delivery_assignments AS da

        INNER JOIN orders AS o
            ON da.order_id = o.id

        INNER JOIN users AS u
            ON o.user_id = u.id

        INNER JOIN dabbawalas AS d
            ON da.dabbawala_id = d.id

        INNER JOIN delivery_tracking AS dt
            ON dt.assignment_id = da.id

        INNER JOIN (
            SELECT
                assignment_id,
                MAX(id) AS latest_tracking_id

            FROM delivery_tracking

            GROUP BY assignment_id

        ) AS latest
            ON dt.id = latest.latest_tracking_id

        ORDER BY dt.updated_at DESC

        LIMIT 5
    ";


    $result = $conn->query($query);

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $delivery_statuses[] = $row;

        }

        $result->free();

    }


} elseif ($database_type === 'pdo') {


    // ======================================================
    // PDO VERSION
    // ======================================================


    // ------------------------------------------------------
    // Total Customers
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM users
    ");

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $total_customers = (int) $row['total'];

    }


    // ------------------------------------------------------
    // Total Dabbawalas
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM dabbawalas
    ");

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $total_dabbawalas = (int) $row['total'];

    }


    // ------------------------------------------------------
    // Total Orders
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM orders
    ");

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $total_orders = (int) $row['total'];

    }


    // ------------------------------------------------------
    // Pending Deliveries
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM delivery_assignments
        WHERE assignment_status IN ('assigned', 'accepted')
    ");

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $pending_deliveries = (int) $row['total'];

    }


    // ------------------------------------------------------
    // Recent Orders
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.total_amount,
            o.order_status,
            o.created_at,
            u.name AS customer_name

        FROM orders AS o

        INNER JOIN users AS u
            ON o.user_id = u.id

        ORDER BY o.created_at DESC

        LIMIT 5
    ");

    $statement->execute();

    $recent_orders = $statement->fetchAll(PDO::FETCH_ASSOC);


    // ------------------------------------------------------
    // Delivery Status
    // ------------------------------------------------------

    $statement = $pdo->prepare("
        SELECT
            da.id AS assignment_id,
            o.order_number,
            u.name AS customer_name,
            d.name AS dabbawala_name,
            dt.status AS delivery_status,
            dt.updated_at

        FROM delivery_assignments AS da

        INNER JOIN orders AS o
            ON da.order_id = o.id

        INNER JOIN users AS u
            ON o.user_id = u.id

        INNER JOIN dabbawalas AS d
            ON da.dabbawala_id = d.id

        INNER JOIN delivery_tracking AS dt
            ON dt.assignment_id = da.id

        INNER JOIN (
            SELECT
                assignment_id,
                MAX(id) AS latest_tracking_id

            FROM delivery_tracking

            GROUP BY assignment_id

        ) AS latest
            ON dt.id = latest.latest_tracking_id

        ORDER BY dt.updated_at DESC

        LIMIT 5
    ");

    $statement->execute();

    $delivery_statuses = $statement->fetchAll(PDO::FETCH_ASSOC);

}


// ==========================================================
// 7. HELPER FUNCTIONS
// ==========================================================

/**
 * Convert order status into Bootstrap badge classes.
 */
function getOrderStatusClass($status)
{
    switch ($status) {

        case 'pending':
            return 'bg-warning text-dark';

        case 'confirmed':
            return 'bg-primary';

        case 'completed':
            return 'bg-success';

        case 'cancelled':
            return 'bg-danger';

        default:
            return 'bg-secondary';
    }
}


/**
 * Convert delivery status into Bootstrap badge classes.
 */
function getDeliveryStatusClass($status)
{
    switch ($status) {

        case 'assigned':
            return 'bg-secondary';

        case 'accepted':
            return 'bg-primary';

        case 'picked_up':
            return 'bg-info text-dark';

        case 'out_for_delivery':
            return 'bg-warning text-dark';

        case 'near_customer':
            return 'bg-warning text-dark';

        case 'delivered':
            return 'bg-success';

        case 'failed':
            return 'bg-danger';

        case 'cancelled':
            return 'bg-danger';

        default:
            return 'bg-secondary';
    }
}


// ==========================================================
// 8. INCLUDE EXISTING ADMIN HEADER
// ==========================================================

require_once __DIR__ . '/includes/admin_header.php';

?>


<!-- =======================================================
     DASHBOARD WELCOME SECTION
======================================================= -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">
            Welcome to Admin Dashboard
        </h2>

        <p class="text-muted mb-0">
            Manage and monitor your Smart Tiffin Delivery System.
        </p>

    </div>

</div>


<!-- =======================================================
     SUMMARY CARDS
======================================================= -->

<div class="row g-4 mb-4">


    <!-- Total Customers -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Total Customers
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?php echo number_format($total_customers); ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 bg-warning-subtle d-flex align-items-center justify-content-center"
                        style="width: 50px; height: 50px;"
                    >
                        <i class="bi bi-people fs-4 text-warning"></i>
                    </div>

                </div>

                <div class="mt-3">

                    <span class="small text-muted">
                        Registered customers
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- Total Dabbawalas -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Total Dabbawalas
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?php echo number_format($total_dabbawalas); ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 bg-primary-subtle d-flex align-items-center justify-content-center"
                        style="width: 50px; height: 50px;"
                    >
                        <i class="bi bi-person-badge fs-4 text-primary"></i>
                    </div>

                </div>

                <div class="mt-3">

                    <span class="small text-muted">
                        Registered delivery staff
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- Total Orders -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Total Orders
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?php echo number_format($total_orders); ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 bg-success-subtle d-flex align-items-center justify-content-center"
                        style="width: 50px; height: 50px;"
                    >
                        <i class="bi bi-cart-check fs-4 text-success"></i>
                    </div>

                </div>

                <div class="mt-3">

                    <span class="small text-muted">
                        Orders placed
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- Pending Deliveries -->

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <p class="text-muted mb-2">
                            Pending Deliveries
                        </p>

                        <h2 class="fw-bold mb-0">
                            <?php echo number_format($pending_deliveries); ?>
                        </h2>

                    </div>

                    <div
                        class="rounded-3 bg-danger-subtle d-flex align-items-center justify-content-center"
                        style="width: 50px; height: 50px;"
                    >
                        <i class="bi bi-truck fs-4 text-danger"></i>
                    </div>

                </div>

                <div class="mt-3">

                    <span class="small text-muted">
                        Assigned or accepted
                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     RECENT ORDERS + DELIVERY STATUS
======================================================= -->

<div class="row g-4">


    <!-- ===================================================
         RECENT ORDERS
    ==================================================== -->

    <div class="col-12 col-xl-7">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white border-0 p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Recent Orders
                        </h5>

                        <p class="text-muted small mb-0">
                            Latest orders placed by customers
                        </p>

                    </div>

                    <i class="bi bi-clock-history fs-4 text-muted"></i>

                </div>

            </div>


            <div class="card-body p-0">

                <?php if (!empty($recent_orders)): ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th class="px-4">
                                        Order
                                    </th>

                                    <th>
                                        Customer
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

                                            <span class="fw-semibold">
                                                <?php
                                                echo htmlspecialchars(
                                                    $order['order_number'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </span>

                                            <div class="small text-muted">

                                                <?php
                                                echo htmlspecialchars(
                                                    date(
                                                        'd M Y',
                                                        strtotime($order['created_at'])
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </div>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $order['customer_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            ₹<?php
                                            echo number_format(
                                                (float) $order['total_amount'],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <span class="badge rounded-pill <?php
                                                echo getOrderStatusClass(
                                                    $order['order_status']
                                                );
                                            ?>">

                                                <?php
                                                echo htmlspecialchars(
                                                    ucfirst($order['order_status']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
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

                    <div class="text-center py-5 px-4">

                        <div
                            class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 70px; height: 70px;"
                        >
                            <i class="bi bi-cart3 fs-3 text-muted"></i>
                        </div>

                        <h6 class="fw-bold">
                            No orders yet
                        </h6>

                        <p class="text-muted small mb-0">
                            Recent customer orders will appear here.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- ===================================================
         DELIVERY STATUS
    ==================================================== -->

    <div class="col-12 col-xl-5">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white border-0 p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Delivery Status
                        </h5>

                        <p class="text-muted small mb-0">
                            Latest delivery activity
                        </p>

                    </div>

                    <i class="bi bi-truck fs-4 text-muted"></i>

                </div>

            </div>


            <div class="card-body">

                <?php if (!empty($delivery_statuses)): ?>

                    <div class="list-group list-group-flush">

                        <?php foreach ($delivery_statuses as $delivery): ?>

                            <div class="list-group-item px-0 py-3">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <div class="fw-semibold">

                                            <?php
                                            echo htmlspecialchars(
                                                $delivery['order_number'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </div>

                                        <div class="small text-muted">

                                            <?php
                                            echo htmlspecialchars(
                                                $delivery['customer_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </div>

                                        <div class="small text-muted mt-1">

                                            Dabbawala:
                                            <?php
                                            echo htmlspecialchars(
                                                $delivery['dabbawala_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </div>

                                    </div>


                                    <span class="badge rounded-pill <?php
                                        echo getDeliveryStatusClass(
                                            $delivery['delivery_status']
                                        );
                                    ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $delivery['delivery_status']
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="text-center py-5">

                        <div
                            class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 70px; height: 70px;"
                        >
                            <i class="bi bi-truck fs-3 text-muted"></i>
                        </div>

                        <h6 class="fw-bold">
                            No delivery activity
                        </h6>

                        <p class="text-muted small mb-0">
                            Delivery updates will appear here once
                            deliveries are assigned and tracked.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<?php

// ==========================================================
// 9. EXISTING ADMIN FOOTER
// ==========================================================

require_once __DIR__ . '/includes/admin_footer.php';

?>