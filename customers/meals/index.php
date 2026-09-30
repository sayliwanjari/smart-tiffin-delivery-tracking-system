<?php
/**
 * Smart Tiffin Delivery & Tracking System
 * Customer Meals
 */

require_once __DIR__ . '/../../customers/includes/customer_auth.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Meals';

// Fetch only available meals
$meals = [];
$db_error = '';

$sql = "SELECT id, name, description, price, image, is_available
        FROM meals
        WHERE is_available = 1
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if ($result === false) {
    $db_error = 'Unable to load meals at the moment.';
} else {
    while ($row = $result->fetch_assoc()) {
        $meals[] = $row;
    }

    $result->free();
}

require_once __DIR__ . '/../../customers/includes/customer_header.php';
?>

<style>
    .meals-page {
        background: #f8fafc;
        min-height: 100vh;
        padding: 35px 0 60px;
    }

    .meals-hero {
        background: linear-gradient(135deg, #fff7ed, #ffffff);
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 35px;
        border: 1px solid rgba(255, 107, 0, 0.08);
    }

    .meals-hero h1 {
        color: #111827;
        font-weight: 800;
        font-size: clamp(2rem, 4vw, 3rem);
        margin-bottom: 10px;
    }

    .meals-hero h1 span {
        color: #ff6b00;
    }

    .meals-hero p {
        color: #6b7280;
        font-size: 1.05rem;
        max-width: 650px;
        margin-bottom: 0;
    }

    .meal-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #fff;
        color: #ff6b00;
        border: 1px solid #fed7aa;
        padding: 8px 14px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .meal-card {
        height: 100%;
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.25s ease;
        box-shadow: 0 5px 20px rgba(17, 24, 39, 0.05);
    }

    .meal-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 14px 35px rgba(17, 24, 39, 0.11);
        border-color: #fed7aa;
    }

    .meal-image-wrapper {
        height: 215px;
        background: linear-gradient(135deg, #fff7ed, #ffedd5);
        overflow: hidden;
        position: relative;
    }

    .meal-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .meal-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ff6b00;
        font-size: 4rem;
    }

    .available-badge {
        position: absolute;
        top: 14px;
        right: 14px;
        background: #dcfce7;
        color: #166534;
        padding: 6px 11px;
        border-radius: 50px;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .meal-content {
        padding: 22px;
    }

    .meal-name {
        color: #111827;
        font-size: 1.2rem;
        font-weight: 750;
        margin-bottom: 8px;
    }

    .meal-description {
        color: #6b7280;
        font-size: 0.92rem;
        line-height: 1.6;
        min-height: 46px;
        margin-bottom: 18px;
    }

    .meal-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .meal-price {
        color: #111827;
        font-size: 1.25rem;
        font-weight: 800;
    }

    .meal-price small {
        color: #6b7280;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .meal-action {
        background: #ff6b00;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 15px;
        font-weight: 650;
        font-size: 0.88rem;
        transition: 0.2s ease;
    }

    .meal-action:hover {
        background: #e85f00;
        color: #fff;
        transform: translateY(-1px);
    }

    .empty-meals {
        background: #fff;
        border-radius: 20px;
        padding: 65px 25px;
        text-align: center;
        border: 1px solid #eef0f3;
    }

    .empty-meals-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: #fff7ed;
        color: #ff6b00;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .error-box {
        border-radius: 15px;
        padding: 18px 20px;
    }

    @media (max-width: 767px) {
        .meals-page {
            padding: 20px 0 40px;
        }

        .meals-hero {
            padding: 25px 20px;
            border-radius: 18px;
        }

        .meal-image-wrapper {
            height: 200px;
        }

        .meal-content {
            padding: 18px;
        }
    }
</style>

<div class="meals-page">
    <div class="container">

        <!-- Hero -->
        <section class="meals-hero">
            <span class="meal-count-badge">
                <i class="bi bi-egg-fried"></i>
                Freshly Prepared
            </span>

            <h1>
                Choose Your <span>Perfect Meal</span>
            </h1>

            <p>
                Explore our freshly prepared tiffin meals made with wholesome
                ingredients and delivered straight to your doorstep.
            </p>
        </section>

        <?php if ($db_error): ?>

            <div class="alert alert-danger error-box" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php elseif (empty($meals)): ?>

            <!-- Empty State -->
            <div class="empty-meals">
                <div class="empty-meals-icon">
                    <i class="bi bi-egg-fried"></i>
                </div>

                <h3 class="fw-bold text-dark">No meals available right now</h3>

                <p class="text-muted mb-0">
                    Our kitchen is preparing something delicious.
                    Please check back soon.
                </p>
            </div>

        <?php else: ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1 text-dark">Today's Meals</h2>
                    <p class="text-muted mb-0">
                        Pick something delicious for your next tiffin.
                    </p>
                </div>

                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                    <?= count($meals) ?> meal<?= count($meals) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <div class="row g-4">

                <?php foreach ($meals as $meal): ?>

                    <?php
                    $meal_name = htmlspecialchars(
                        $meal['name'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $meal_description = htmlspecialchars(
                        $meal['description'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $price = number_format(
                        (float)($meal['price'] ?? 0),
                        2
                    );

                    $image = trim((string)($meal['image'] ?? ''));

                    /*
                     * The image column may contain either:
                     * - a relative path such as uploads/meals/meal.jpg
                     * - a filename such as meal.jpg
                     *
                     * We only display it as provided by the database.
                     */
                    $image_url = '';

                    if ($image !== '') {
                        if (
                            strpos($image, 'http://') === 0 ||
                            strpos($image, 'https://') === 0
                        ) {
                            $image_url = $image;
                        } else {
                            $image_url = '../../' . ltrim($image, '/');
                        }
                    }
                    ?>

                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="meal-card">

                            <!-- Meal Image -->
                            <div class="meal-image-wrapper">

                                <?php if ($image_url !== ''): ?>

                                    <img
                                        src="<?= htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= $meal_name ?>"
                                        class="meal-image"
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    >

                                    <div
                                        class="meal-placeholder"
                                        style="display:none;"
                                    >
                                        <i class="bi bi-egg-fried"></i>
                                    </div>

                                <?php else: ?>

                                    <div class="meal-placeholder">
                                        <i class="bi bi-egg-fried"></i>
                                    </div>

                                <?php endif; ?>

                                <span class="available-badge">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    Available
                                </span>

                            </div>

                            <!-- Meal Information -->
                            <div class="meal-content">

                                <h3 class="meal-name">
                                    <?= $meal_name ?>
                                </h3>

                                <p class="meal-description">
                                    <?php if ($meal_description !== ''): ?>
                                        <?= $meal_description ?>
                                    <?php else: ?>
                                        Delicious freshly prepared tiffin meal.
                                    <?php endif; ?>
                                </p>

                                <div class="meal-bottom">

                                    <div class="meal-price">
                                        ₹<?= $price ?>
                                        <small>/ meal</small>
                                    </div>

                                    <!--
                                        Meals are currently display-only.
                                        Order functionality will be connected
                                        in the Order module.
                                    -->
                                    <a href="../orders/add.php?meal_id=<?= (int)$meal['id'] ?>" class="btn btn-warning">
    <i class="bi bi-plus-circle me-1"></i> Select
</a>

                                </div>

                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</div>

<?php
require_once __DIR__ . '/../../customers/includes/customer_footer.php';
?>