<?php
$current_page = $active_page ?? 'dashboard';
?>

<aside class="dabbawala-sidebar" id="dabbawalaSidebar">

    <div class="sidebar-brand">
        <a href="<?= htmlspecialchars(
            '/mumbaiDabbawala/dabbawalas/index.php',
            ENT_QUOTES,
            'UTF-8'
        ); ?>">
            Smart <span>Tiffin</span>
        </a>
    </div>

    <div class="sidebar-user">

        <div class="user-avatar">
            <?= dabbawala_e($initial ?? 'D'); ?>
        </div>

        <div>
            <div class="sidebar-user-name">
                <?= dabbawala_e($dabbawala_name ?? 'Dabbawala'); ?>
            </div>

            <div class="sidebar-user-role">
                Delivery Partner
            </div>
        </div>

    </div>

    <nav class="sidebar-menu">

        <div class="sidebar-label">
            Main Menu
        </div>

        <a
            href="/mumbaiDabbawala/dabbawalas/index.php"
            class="sidebar-link <?= $current_page === 'dashboard' ? 'active' : ''; ?>"
        >
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <a
            href="/mumbaiDabbawala/dabbawalas/delivaries/index.php"
            class="sidebar-link <?= $current_page === 'deliveries' ? 'active' : ''; ?>"
        >
            <i class="bi bi-box-seam-fill"></i>
            <span>My Deliveries</span>
        </a>

        <a
            href="/mumbaiDabbawala/dabbawalas/gps.php"
            class="sidebar-link <?= $current_page === 'gps' ? 'active' : ''; ?>"
        >
            <i class="bi bi-geo-alt-fill"></i>
            <span>GPS Tracking</span>
        </a>

        <a
            href="/mumbaiDabbawala/dabbawalas/history.php"
            class="sidebar-link <?= $current_page === 'history' ? 'active' : ''; ?>"
        >
            <i class="bi bi-clock-history"></i>
            <span>Delivery History</span>
        </a>

        <div class="sidebar-label mt-3">
            Account
        </div>

        <a
            href="/mumbaiDabbawala/dabbawalas/profile.php"
            class="sidebar-link <?= $current_page === 'profile' ? 'active' : ''; ?>"
        >
            <i class="bi bi-person-circle"></i>
            <span>My Profile</span>
        </a>

        <a
            href="/mumbaiDabbawala/dabbawalas/logout.php"
            class="sidebar-link"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </nav>

</aside>