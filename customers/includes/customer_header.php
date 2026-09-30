 <?php

/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    require_once __DIR__ . '/customer_auth.php';
}


/*
|--------------------------------------------------------------------------
| Customer Session Data
|--------------------------------------------------------------------------
*/

$customer_id = (int) ($_SESSION['customer_id'] ?? 0);

$customer_name = $_SESSION['customer_name'] ?? 'Customer';

$customer_email = $_SESSION['customer_email'] ?? '';


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$page_title = $page_title ?? 'Smart Tiffin';

$active_page = $active_page ?? 'home';


/*
|--------------------------------------------------------------------------
| Escape Function
|--------------------------------------------------------------------------
*/

function customer_e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Customer Initial
|--------------------------------------------------------------------------
*/

$initial = strtoupper(
    substr(
        trim($customer_name),
        0,
        1
    )
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo customer_e($page_title); ?> | Smart Tiffin
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="/mumbaiDabbawala/assets/css/customer.css"
    >

</head>

<body class="customer-body">

<nav class="navbar navbar-expand-lg customer-navbar sticky-top">

    <div class="container">

        <a
            class="navbar-brand customer-brand"
            href="index.php"
        >

            <span class="brand-small-icon">
                <i class="bi bi-egg-fried"></i>
            </span>

            Smart Tiffin

        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#customerNavbar"
        >
            <i class="bi bi-list"></i>
        </button>


        <div
            class="collapse navbar-collapse"
            id="customerNavbar"
        >

            <ul class="navbar-nav mx-auto">

                <li class="nav-item">
                    <a
                        class="nav-link <?php echo $active_page === 'home' ? 'active' : ''; ?>"
                        href="/mumbaiDabbawala/customers/index.php"
                    >
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="/mumbaiDabbawala/customers/meals/index.php"
                    >
                        Meals
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="/mumbaiDabbawala/customers/plans/index.php"
                    >
                        Plans
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="/mumbaiDabbawala/customers/orders/index.php"
                    >
                        My Orders
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="/mumbaiDabbawala/customers/tracking.php"
                    >
                        Track Delivery
                    </a>
                </li>

            </ul>


            <div class="dropdown">

                <button
                    class="customer-profile-button dropdown-toggle"
                    data-bs-toggle="dropdown"
                >

                    <span class="customer-avatar">
                        <?php echo customer_e($initial); ?>
                    </span>

                    <span class="d-none d-md-inline">
                        <?php echo customer_e($customer_name); ?>
                    </span>

                </button>


                <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                    <li>
                        <a
                            class="dropdown-item"
                            href="/mumbaiDabbawala/customers/profile.php"
                        >
                            <i class="bi bi-person me-2"></i>
                            My Profile
                        </a>
                    </li>

                    <li>
                        <a
                            class="dropdown-item"
                            href="orders/"
                        >
                            <i class="bi bi-receipt me-2"></i>
                            My Orders
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <a
                            class="dropdown-item text-danger"
                            href="logout.php"
                        >
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Logout
                        </a>
                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>


<main class="customer-main">