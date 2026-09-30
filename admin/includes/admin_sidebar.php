
<!-- Desktop Sidebar -->
<aside class="admin-sidebar d-none d-lg-flex flex-column">

    <!-- Logo / Brand -->
    <div class="sidebar-brand p-4">

        <a
            href="../admin/dashboard.php"
            class="text-decoration-none"
        >

            <div class="d-flex align-items-center gap-2">

                <span
                    class="brand-icon rounded-3 d-flex align-items-center justify-content-center"
                    style="width: 42px; height: 42px;"
                >
                    <i class="bi bi-box-seam-fill fs-4"></i>
                </span>

                <div>

                    <div class="fw-bold fs-5">
                        Smart Tiffin
                    </div>

                    <small class="text-muted">
                        Admin Panel
                    </small>

                </div>

            </div>

        </a>

    </div>


    <!-- Navigation -->
    <div class="sidebar-menu flex-grow-1 px-3">

        <div class="text-uppercase small text-muted fw-semibold px-3 mb-2">
            Main
        </div>


        <!-- Dashboard -->
        <a
            href="/mumbaiDabbawala/admin/dashboard.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?>"
        >
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>


        <!-- Management -->
        <div class="text-uppercase small text-muted fw-semibold px-3 mt-4 mb-2">
            Management
        </div>


        <!-- Customers -->
        <a
    href="/mumbaiDabbawala/admin/customers/index.php"
    class="sidebar-link <?php echo ($active_page ?? '') === 'customers' ? 'active' : ''; ?>"
>
    <i class="bi bi-people"></i>
    <span>Customers</span>
</a>

        <!-- Dabbawalas -->
        <a
            href="/mumbaiDabbawala/admin/dabbawalas/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'dabbawalas' ? 'active' : ''; ?>"
        >
            <i class="bi bi-person-badge"></i>
            <span>Dabbawalas</span>
        </a>


        
        <a
            href="/mumbaiDabbawala/admin/meals/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'meals' ? 'active' : ''; ?>"
        >
            <i class="bi bi-egg-fried"></i>
            <span>Meals</span>
        </a>


        

        <a
            href="/mumbaiDabbawala/admin/subscription/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'subscription' ? 'active' : ''; ?>"
        >
            <i class="bi bi-card-checklist"></i>
            <span>Subscriptions</span>
        </a>


         <a
            href="/mumbaiDabbawala/admin/orders/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'ordres' ? 'active' : ''; ?>"
        >
            <i class="bi bi-cart3"></i>
            <span>Oreders</span>
        </a>


        <a
            href="/mumbaiDabbawala/admin/deliveries/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'deliveries' ? 'active' : ''; ?>"
        >
            <i class="bi bi-truck"></i>
            <span>Diliveries</span>
        </a>

        <a
    href="<?php echo $base_path; ?>/admin/deliveries/map.php"
    class="nav-link"
>

        <a
            href="/mumbaiDabbawala/admin/reports/index.php"
            class="sidebar-link <?php echo ($active_page ?? '') === 'reports' ? 'active' : ''; ?>"
        >
            <i class="bi bi-bar-chart"></i>
            <span>Reports</span>
        </a>


    </div>


    <!-- Sidebar Bottom -->
    <div class="sidebar-footer p-3 border-top">

        <a
            href="../auth/logout.php"
            class="sidebar-link text-danger"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>


<!-- Mobile Offcanvas Sidebar -->
<div
    class="offcanvas offcanvas-start"
    tabindex="-1"
    id="adminSidebar"
    aria-labelledby="adminSidebarLabel"
>

    <div class="offcanvas-header border-bottom">

        <div>

            <h5
                class="offcanvas-title fw-bold"
                id="adminSidebarLabel"
            >
                Smart Tiffin
            </h5>

            <small class="text-muted">
                Admin Panel
            </small>

        </div>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="offcanvas"
            aria-label="Close"
        ></button>

    </div>


    <div class="offcanvas-body p-0">

        <div class="p-3">

            <!-- Dashboard -->
            <a
                href="../admin/dashboard.php"
                class="sidebar-link <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?>"
            >
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>


            <!-- Customers -->
            <a
                href="../admin/customers/index.php"
                class="sidebar-link <?php echo ($active_page ?? '') === 'customers' ? 'active' : ''; ?>"
            >
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>


            <!-- Dabbawalas -->
            <a
                href="../admin/dabbawalas/index.php"
                class="sidebar-link <?php echo ($active_page ?? '') === 'dabbawalas' ? 'active' : ''; ?>"
            >
                <i class="bi bi-person-badge"></i>
                <span>Dabbawalas</span>
            </a>


            <hr>


            <div class="text-uppercase small text-muted fw-semibold px-3 mb-2">
                Coming Later
            </div>


            <!-- Future functionality intentionally disabled -->
            <div class="sidebar-link disabled opacity-50">
                <i class="bi bi-egg-fried"></i>
                <span>Meals</span>
            </div>

            <div class="sidebar-link disabled opacity-50">
                <i class="bi bi-card-checklist"></i>
                <span>Subscriptions</span>
            </div>

            <div class="sidebar-link disabled opacity-50">
                <i class="bi bi-cart3"></i>
                <span>Orders</span>
            </div>

            <div class="sidebar-link disabled opacity-50">
                <i class="bi bi-truck"></i>
                <span>Deliveries</span>
            </div>

            <div class="sidebar-link disabled opacity-50">
                <i class="bi bi-bar-chart"></i>
                <span>Reports</span>
            </div>


            <hr>


            <!-- Logout -->
            <a
                href="../auth/logout.php"
                class="sidebar-link text-danger"
            >
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>

        </div>

    </div>

</div>