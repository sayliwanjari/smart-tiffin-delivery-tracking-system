<?php



?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Smart Tiffin Delivery & Tracking System - Admin Panel">

    <title>
        <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Admin Dashboard'; ?>
        | Smart Tiffin
    </title>

    <!-- Bootstrap 5 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


         <link rel="stylesheet" href="assets/css/admin.css">

    <!-- Main CSS -->
     <link rel="stylesheet" href="/mumbaiDabbawala/assets/css/admin.css">
    

</head>

<body>

    <!-- Admin Application Wrapper -->
    <div class="admin-wrapper">

        <!-- Sidebar -->
        <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="admin-main">

            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm admin-navbar">

                <div class="container-fluid">

                    <!-- Mobile Sidebar Button -->
                    <button
                        class="btn btn-outline-secondary d-lg-none me-2"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#adminSidebar"
                        aria-controls="adminSidebar"
                    >
                        <i class="bi bi-list fs-4"></i>
                    </button>

                    <!-- Page Title -->
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?>
                        </h5>
                    </div>

                    <!-- Right Side -->
                    <div class="ms-auto d-flex align-items-center gap-3">

                        <!-- Notification Icon -->
                        <button
                            type="button"
                            class="btn btn-light position-relative"
                            title="Notifications"
                        >
                            <i class="bi bi-bell fs-5"></i>

                            <!-- Notification badge will be implemented later -->
                        </button>

                        <!-- Admin Profile -->
                        <div class="dropdown">

                            <button
                                class="btn btn-light dropdown-toggle d-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >

                                <span
                                    class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center"
                                    style="width: 38px; height: 38px;"
                                >
                                    <i class="bi bi-person-fill text-warning"></i>
                                </span>

                                <span class="d-none d-md-inline">
                                    Admin
                                </span>

                            </button>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="../admin/profile.php"
                                    >
                                        <i class="bi bi-person me-2"></i>
                                        My Profile
                                    </a>
                                </li>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>

                                <li>
                                    <a
                                        class="dropdown-item text-danger"
                                        href="../auth/logout.php"
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

            <!-- Page Content Starts -->
            <main class="admin-content">

                <div class="container-fluid py-4">