<?php






require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/customer_auth.php';

$page_title = 'My Subscription';
$active_page = 'subscription';

$subscriptions = [];

$sql = "SELECT
            s.id,
            s.start_date,
            s.end_date,
            s.status,
            s.meals_remaining,
            s.paused_at,
            s.resumed_at,
            s.created_at,
            p.name AS plan_name,
            p.description AS plan_description,
            p.duration_days,
            p.total_meals,
            p.price
        FROM subscriptions s
        INNER JOIN subscription_plans p
            ON p.id = s.plan_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }

    $stmt->close();
}

require_once __DIR__ . '/includes/customer_header.php';
?>

<style>
    .subscription-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 35px 0 60px;
    }

    .subscription-heading {
        margin-bottom: 30px;
    }

    .subscription-heading h1 {
        color: #111827;
        font-weight: 800;
    }

    .subscription-heading p {
        color: #6b7280;
    }

    .subscription-card {
        background: #fff;
        border-radius: 25px;
        padding: 28px;
        border: 1px solid #eef0f3;
        box-shadow: 0 12px 32px rgba(17,24,39,.06);
        margin-bottom: 22px;
    }

    .subscription-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
    }

    .subscription-icon {
        width: 58px;
        height: 58px;
        border-radius: 17px;
        background: #fff3e8;
        color: #ff6b00;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 25px;
        flex-shrink: 0;
    }

    .subscription-title {
        font-weight: 800;
        color: #111827;
        margin-bottom: 5px;
    }

    .subscription-description {
        color: #6b7280;
        margin-bottom: 0;
    }

    .subscription-price {
        font-size: 1.5rem;
        font-weight: 800;
        color: #ff6b00;
        white-space: nowrap;
    }

    .subscription-details {
        margin-top: 25px;
        padding-top: 22px;
        border-top: 1px solid #eef0f3;
    }

    .detail-box {
        background: #f8fafc;
        border-radius: 15px;
        padding: 16px;
        height: 100%;
    }

    .detail-label {
        color: #6b7280;
        font-size: .8rem;
        margin-bottom: 5px;
    }

    .detail-value {
        color: #111827;
        font-weight: 700;
    }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-paused {
        background: #fef3c7;
        color: #92400e;
    }

    .status-expired {
        background: #e5e7eb;
        color: #374151;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-badge {
        border-radius: 50px;
        padding: 7px 13px;
        font-size: .78rem;
        font-weight: 700;
        text-transform: capitalize;
    }

    .empty-subscription {
        background: #fff;
        border-radius: 25px;
        padding: 65px 25px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(17,24,39,.05);
    }

    .empty-subscription i {
        font-size: 55px;
        color: #ff6b00;
    }

    .browse-plans-btn {
        background: #ff6b00;
        color: #fff;
        border-radius: 12px;
        padding: 12px 20px;
        font-weight: 700;
        display: inline-block;
        text-decoration: none;
        margin-top: 15px;
    }

    .browse-plans-btn:hover {
        background: #e85d00;
        color: #fff;
    }

    @media (max-width: 767px) {
        .subscription-top {
            flex-direction: column;
        }

        .subscription-price {
            font-size: 1.25rem;
        }
    }
</style>

<div class="subscription-page">
    <div class="container">

        <div class="subscription-heading">
            <h1>
                <i class="bi bi-calendar-heart me-2" style="color:#ff6b00;"></i>
                My Subscription
            </h1>
            <p class="mb-0">
                Manage and view your Smart Tiffin plans.
            </p>
        </div>

        <?php if (empty($subscriptions)): ?>

            <div class="empty-subscription">

                <i class="bi bi-calendar-plus"></i>

                <h3 class="fw-bold mt-3">
                    No subscription yet
                </h3>

                <p class="text-muted">
                    Choose a tiffin plan and enjoy fresh meals delivered to your doorstep.
                </p>

                <a href="plans/index.php" class="browse-plans-btn">
                    <i class="bi bi-grid me-1"></i>
                    Browse Plans
                </a>

            </div>

        <?php else: ?>

            <?php foreach ($subscriptions as $subscription): ?>

                <?php
                $status = strtolower(trim((string)$subscription['status']));

                $status_class = match ($status) {
                    'active' => 'status-active',
                    'paused' => 'status-paused',
                    'expired' => 'status-expired',
                    'cancelled' => 'status-cancelled',
                    default => 'status-expired'
                };
                ?>

                <div class="subscription-card">

                    <div class="subscription-top">

                        <div class="d-flex gap-3">

                            <div class="subscription-icon">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>

                            <div>
                                <h3 class="subscription-title">
                                    <?= htmlspecialchars(
                                        $subscription['plan_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <p class="subscription-description">
                                    <?= htmlspecialchars(
                                        $subscription['plan_description'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>
                            </div>

                        </div>

                        <div class="text-md-end">

                            <div class="subscription-price">
                                ₹<?= number_format(
                                    (float)$subscription['price'],
                                    2
                                ) ?>
                            </div>

                            <span class="status-badge <?= $status_class ?>">
                                <?= htmlspecialchars(
                                    ucfirst($status),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    </div>

                    <div class="subscription-details">

                        <div class="row g-3">

                            <div class="col-6 col-lg-3">
                                <div class="detail-box">
                                    <div class="detail-label">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        Start Date
                                    </div>

                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($subscription['start_date'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="detail-box">
                                    <div class="detail-label">
                                        <i class="bi bi-calendar-check me-1"></i>
                                        End Date
                                    </div>

                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($subscription['end_date'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="detail-box">
                                    <div class="detail-label">
                                        <i class="bi bi-egg-fried me-1"></i>
                                        Total Meals
                                    </div>

                                    <div class="detail-value">
                                        <?= (int)$subscription['total_meals'] ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-lg-3">
                                <div class="detail-box">
                                    <div class="detail-label">
                                        <i class="bi bi-egg-fill me-1"></i>
                                        Meals Remaining
                                    </div>

                                    <div class="detail-value">
                                        <?= (int)$subscription['meals_remaining'] ?>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/customer_footer.php'; ?>