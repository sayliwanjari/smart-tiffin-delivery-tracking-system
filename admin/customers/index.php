<?php
/**
 * Smart Tiffin Delivery & Tracking System
 *
 * Module 2 - Step 4
 * Customer List
 *
 * This page only displays customers.
 *
 * NOT IMPLEMENTED YET:
 * - Add Customer
 * - Edit Customer
 * - Search
 * - Filter
 * - Activate / Deactivate
 * - Delete
 */


// ==========================================================
// 1. EXISTING SESSION / AUTHENTICATION
// ==========================================================

require_once __DIR__ . '/../../config/session.php';


// ==========================================================
// 2. EXISTING DATABASE CONNECTION
// ==========================================================

require_once __DIR__ . '/../../config/database.php';


// ==========================================================
// 3. PAGE SETTINGS
// ==========================================================

$page_title = 'Customers';

$active_page = 'customers';


// ==========================================================
// 4. VARIABLES
// ==========================================================

$customers = [];

$customer_count = 0;

$error_message = '';


// ==========================================================
// 5. CHECK EXISTING DATABASE CONNECTION
// ==========================================================
//
// We use the connection created by config/database.php.
//
// No new connection is created here.
//

if (isset($conn) && $conn instanceof mysqli) {

    $database_type = 'mysqli';

} elseif (isset($pdo) && $pdo instanceof PDO) {

    $database_type = 'pdo';

} else {

    $database_type = null;

    $error_message = 'Database connection is not available.';

}


// ==========================================================
// 6. FETCH CUSTOMERS
// ==========================================================

if ($database_type === 'mysqli') {

    /*
     * We use a prepared statement even though this SELECT
     * currently has no user-provided input.
     *
     * This keeps the page ready for future search/filter
     * functionality.
     */

    $sql = "
        SELECT
            id,
            name,
            email,
            phone,
            status,
            created_at
        FROM users
        ORDER BY created_at DESC
    ";

    $statement = $conn->prepare($sql);


    if ($statement) {

        if ($statement->execute()) {

            $result = $statement->get_result();

            if ($result) {

                $customers = $result->fetch_all(MYSQLI_ASSOC);

                $customer_count = count($customers);

            }

        } else {

            $error_message = 'Unable to load customers.';

        }

        $statement->close();

    } else {

        $error_message = 'Unable to prepare the customer query.';

    }


} elseif ($database_type === 'pdo') {

    try {

        $sql = "
            SELECT
                id,
                name,
                email,
                phone,
                status,
                created_at
            FROM users
            ORDER BY created_at DESC
        ";

        $statement = $pdo->prepare($sql);

        $statement->execute();

        $customers = $statement->fetchAll(PDO::FETCH_ASSOC);

        $customer_count = count($customers);

    } catch (PDOException $exception) {

        $error_message = 'Unable to load customers.';

    }

}


// ==========================================================
// 7. INCLUDE EXISTING ADMIN HEADER
// ==========================================================

require_once __DIR__ . '/../includes/admin_header.php';

?>


<!-- =======================================================
     PAGE HEADER
======================================================= -->

<div class="admin-page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h2>
                Customers
            </h2>

            <p class="text-muted">
                View and manage registered Smart Tiffin customers.
            </p>

        </div>


        <!-- Add Customer Button -->
        <!-- Functionality will be implemented later -->

        <div>

            <button
    type="button"
    class="btn btn-primary"
    onclick="window.location.href='/mumbaiDabbawala/admin/customers/add.php';">
    Add Customer
</button>

        </div>

    </div>

</div>


<!-- =======================================================
     ERROR MESSAGE
======================================================= -->

<?php if (!empty($error_message)): ?>

    <div
        class="alert alert-danger d-flex align-items-center"
        role="alert"
    >

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        <div>

            <?php
            echo htmlspecialchars(
                $error_message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

    </div>

<?php endif; ?>


<!-- =======================================================
     CUSTOMER DATA CARD
======================================================= -->

<div class="admin-data-card">


    <!-- Card Header -->

    <div class="admin-data-card-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5>
                    Customer List
                </h5>

                <small class="text-muted">

                    <?php echo number_format($customer_count); ?>

                    customer<?php echo $customer_count === 1 ? '' : 's'; ?>

                    registered

                </small>

            </div>


            <!-- Customer Count -->

            <span class="badge bg-warning text-dark">

                <i class="bi bi-people me-1"></i>

                <?php echo number_format($customer_count); ?>

            </span>

        </div>

    </div>


    <!-- Card Body -->

    <div class="card-body p-0">


        <?php if (!empty($customers)): ?>


            <!-- =================================================
                 RESPONSIVE TABLE
            ================================================== -->

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">


                    <!-- Table Header -->

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                ID
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th class="text-center">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <!-- Table Body -->

                    <tbody>

                        <?php foreach ($customers as $customer): ?>

                            <tr>


                                <!-- ID -->

                                <td class="px-4">

                                    <span class="fw-semibold">

                                        <?php
                                        echo (int) $customer['id'];
                                        ?>

                                    </span>

                                </td>


                                <!-- Customer Name -->

                                <td>

                                    <div class="d-flex align-items-center gap-2">


                                        <!-- Avatar -->

                                        <div
                                            class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 40px; height: 40px;"
                                        >

                                            <i class="bi bi-person text-warning"></i>

                                        </div>


                                        <div>

                                            <div class="fw-semibold">

                                                <?php
                                                echo htmlspecialchars(
                                                    $customer['name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </div>

                                            <small class="text-muted">
                                                Customer
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- Email -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $customer['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <!-- Phone -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $customer['phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <?php if ($customer['status'] === 'active'): ?>

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <i class="bi bi-pause-circle me-1"></i>

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Created Date -->

                                <td>

                                    <span class="text-muted">

                                        <?php
                                        echo htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($customer['created_at'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- Actions -->

                                <td class="text-center">

                                    <div class="d-inline-flex gap-1">


                                        <!-- View -->

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            disabled
                                            title="View functionality will be implemented later"
                                        >

                                            <i class="bi bi-eye"></i>

                                            <span class="d-none d-xl-inline ms-1">
                                                View
                                            </span>

                                        </button>


                                        <!-- Edit -->

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            disabled
                                            title="Edit functionality will be implemented later"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            <span class="d-none d-xl-inline ms-1">
                                                Edit
                                            </span>

                                        </button>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>

                    </tbody>


                </table>

            </div>


        <?php else: ?>


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <div class="admin-empty-state">


                <div class="admin-empty-state-icon">

                    <i class="bi bi-people"></i>

                </div>


                <h5 class="fw-bold">
                    No customers found
                </h5>


                <p class="text-muted mb-0">

                    There are currently no registered customers
                    in the system.

                </p>


            </div>


        <?php endif; ?>


    </div>

</div>


<?php

// ==========================================================
// 8. EXISTING ADMIN FOOTER
// ==========================================================

require_once __DIR__ . '/../includes/admin_footer.php';

?>