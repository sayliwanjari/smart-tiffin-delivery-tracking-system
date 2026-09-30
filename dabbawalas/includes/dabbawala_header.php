<?php

/*
|--------------------------------------------------------------------------
| Dabbawala Header
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$page_title = $page_title ?? 'Dabbawala Dashboard';
$active_page = $active_page ?? 'dashboard';

/*
|--------------------------------------------------------------------------
| Make sure authentication variables exist
|--------------------------------------------------------------------------
*/

$dabbawala_id = $dabbawala_id ?? (int)($_SESSION['dabbawala_id'] ?? 0);

$dabbawala_name = $dabbawala_name
    ?? ($_SESSION['dabbawala_name'] ?? 'Dabbawala');

$dabbawala_email = $dabbawala_email
    ?? ($_SESSION['dabbawala_email'] ?? '');

$dabbawala_availability_status = $dabbawala_availability_status
    ?? ($_SESSION['dabbawala_availability_status'] ?? 'available');


/*
|--------------------------------------------------------------------------
| Escape helper
|--------------------------------------------------------------------------
*/

if (!function_exists('dabbawala_e')) {

    function dabbawala_e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| User Initial
|--------------------------------------------------------------------------
*/

$initial = strtoupper(
    substr(
        trim($dabbawala_name),
        0,
        1
    )
);

if ($initial === '') {
    $initial = 'D';
}

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
        <?= dabbawala_e($page_title); ?> | Smart Tiffin
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --st-orange: #ff6b00;
            --st-secondary: #f59e0b;
            --st-dark: #111827;
            --st-light: #f8fafc;
            --st-border: #e5e7eb;
            --st-sidebar: #111827;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--st-light);
            color: var(--st-dark);
            font-family:
                Inter,
                Arial,
                sans-serif;
        }

        /* SIDEBAR */

        .dabbawala-sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: 250px;
            height: 100vh;

            background: var(--st-sidebar);

            z-index: 1050;

            overflow-y: auto;

            transition: 0.3s ease;
        }

        .sidebar-brand {

            padding: 24px 20px;

            border-bottom:
                1px solid rgba(255,255,255,.08);
        }

        .sidebar-brand a {

            text-decoration: none;

            color: white;

            font-weight: 800;

            font-size: 22px;
        }

        .sidebar-brand span {

            color: var(--st-orange);
        }

        .sidebar-user {

            padding: 20px;

            border-bottom:
                1px solid rgba(255,255,255,.08);

            display: flex;

            align-items: center;

            gap: 12px;
        }

        .user-avatar {

            width: 44px;
            height: 44px;

            min-width: 44px;

            border-radius: 50%;

            background: var(--st-orange);

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;
        }

        .sidebar-user-name {

            color: white;

            font-size: 14px;

            font-weight: 700;
        }

        .sidebar-user-role {

            color: #9ca3af;

            font-size: 12px;
        }

        .sidebar-menu {

            padding: 18px 12px;
        }

        .sidebar-label {

            color: #6b7280;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .08em;

            padding: 10px 12px;
        }

        .sidebar-link {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px 14px;

            margin-bottom: 5px;

            border-radius: 10px;

            color: #d1d5db;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            transition: .2s ease;
        }

        .sidebar-link:hover {

            background:
                rgba(255,255,255,.07);

            color: white;
        }

        .sidebar-link.active {

            background: var(--st-orange);

            color: white;
        }

        .sidebar-link i {

            font-size: 18px;

            width: 22px;
        }

        /* MAIN */

        .main-wrapper {

            margin-left: 250px;

            min-height: 100vh;
        }

        /* TOPBAR */

        .topbar {

            height: 72px;

            background: white;

            border-bottom:
                1px solid var(--st-border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 28px;

            position: sticky;

            top: 0;

            z-index: 1000;
        }

        .mobile-menu-btn {

            display: none;

            border: none;

            background: transparent;

            font-size: 25px;
        }

        .topbar-title {

            font-size: 20px;

            font-weight: 800;
        }

        .topbar-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }

        .topbar-user {

            display: flex;

            align-items: center;

            gap: 10px;
        }

        .topbar-avatar {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #fff1e8;

            color: var(--st-orange);

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;
        }

        /* CONTENT */

        .content-area {

            padding: 30px;
        }

        .page-heading {

            margin-bottom: 25px;
        }

        .page-heading h1 {

            font-size: 28px;

            font-weight: 800;

            margin-bottom: 5px;
        }

        .page-heading p {

            color: #6b7280;

            margin: 0;
        }

        /* MOBILE */

        @media (max-width: 991px) {

            .dabbawala-sidebar {

                left: -250px;
            }

            .dabbawala-sidebar.show {

                left: 0;
            }

            .main-wrapper {

                margin-left: 0;
            }

            .mobile-menu-btn {

                display: block;
            }

            .content-area {

                padding: 20px;
            }
        }

        @media (max-width: 575px) {

            .topbar {

                padding: 0 16px;
            }

            .topbar-user-name {

                display: none;
            }

            .content-area {

                padding: 15px;
            }
        }

    </style>

</head>

<body>

<?php

/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

$sidebar_file = __DIR__ . '/dabbawala_sidebar.php';

if (file_exists($sidebar_file)) {

    require $sidebar_file;

} else {

    echo '<div style="
        background:#dc3545;
        color:white;
        padding:15px;
        margin:10px;
        border-radius:8px;
        font-family:Arial;
    ">
        Error: dabbawala_sidebar.php not found.
    </div>';
}

?>

<div class="main-wrapper">

    <!-- TOPBAR -->

    <header class="topbar">

        <div class="d-flex align-items-center gap-2">

            <button
                type="button"
                class="mobile-menu-btn"
                id="sidebarToggle"
                aria-label="Toggle navigation"
            >

                <i class="bi bi-list"></i>

            </button>

            <div class="topbar-title">

                <?= dabbawala_e($page_title); ?>

            </div>

        </div>


        <div class="topbar-right">

            <div class="topbar-user">

                <div class="topbar-avatar">

                    <?= dabbawala_e($initial); ?>

                </div>

                <div class="topbar-user-name">

                    <?= dabbawala_e($dabbawala_name); ?>

                </div>

            </div>

        </div>

    </header>


    <!-- PAGE CONTENT START -->

    <main class="content-area">