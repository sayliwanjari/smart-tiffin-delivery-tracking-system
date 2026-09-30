<?php
/**
 * Smart Tiffin Delivery & Tracking System
 * Customer - Subscription Plans
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/customer_auth.php';

$page_title = 'Tiffin Plans';
$active_page = 'plans';
$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| SELECT SUBSCRIPTION PLAN
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf_token
        )
    ) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {

        $plan_id = filter_input(
            INPUT_POST,
            'plan_id',
            FILTER_VALIDATE_INT
        );

        if (!$plan_id || $plan_id <= 0) {

            $message = 'Please select a valid subscription plan.';
            $message_type = 'danger';

        } else {

            /*
             * IMPORTANT:
             * Get the plan from the database.
             * Never trust price/duration/meal values sent
             * from the browser.
             */
            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    description,
                    duration_days,
                    total_meals,
                    price,
                    status
                FROM subscription_plans
                WHERE id = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $message = 'Unable to process the selected plan.';
                $message_type = 'danger';

            } else {

                $stmt->bind_param("i", $plan_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $plan = $result->fetch_assoc();

                $stmt->close();

                if (!$plan) {

                    $message = 'The selected subscription plan does not exist.';
                    $message_type = 'danger';

                } elseif ($plan['status'] !== 'active') {

                    $message = 'This subscription plan is currently unavailable.';
                    $message_type = 'danger';

                } elseif (
                    !is_numeric($plan['duration_days']) ||
                    (int)$plan['duration_days'] <= 0
                ) {

                    $message = 'This subscription plan has an invalid duration.';
                    $message_type = 'danger';

                } elseif (
                    !is_numeric($plan['total_meals']) ||
                    (int)$plan['total_meals'] <= 0
                ) {

                    $message = 'This subscription plan has an invalid meal quantity.';
                    $message_type = 'danger';

                } else {

                    $customer_id = (int)$customer_id;

                    /*
                     * Start subscription today.
                     */
                    $start_date = date('Y-m-d');

                    /*
                     * Duration is retrieved from MySQL.
                     *
                     * Example:
                     * 30 days => start date + 29 days
                     */
                    $duration_days = (int)$plan['duration_days'];

                    $end_date = date(
                        'Y-m-d',
                        strtotime(
                            $start_date . ' +' . ($duration_days - 1) . ' days'
                        )
                    );

                    $total_meals = (int)$plan['total_meals'];

                    /*
                     * Use a transaction because subscription
                     * creation is a database operation that should
                     * complete as one unit.
                     */
                    $conn->begin_transaction();

                    try {

                        $insert = $conn->prepare("
                            INSERT INTO subscriptions
                            (
                                user_id,
                                plan_id,
                                start_date,
                                end_date,
                                status,
                                meals_remaining
                            )
                            VALUES (?, ?, ?, ?, 'active', ?)
                        ");

                        if (!$insert) {
                            throw new Exception('Unable to prepare subscription query.');
                        }

                        $insert->bind_param(
                            "iissi",
                            $customer_id,
                            $plan_id,
                            $start_date,
                            $end_date,
                            $total_meals
                        );

                        if (!$insert->execute()) {
                            throw new Exception('Unable to create subscription.');
                        }

                        $insert->close();

                        $conn->commit();

                        /*
                         * Regenerate CSRF token after successful POST.
                         */
                        $_SESSION['csrf_token'] = bin2hex(
                            random_bytes(32)
                        );

                        /*
                         * Redirect to avoid duplicate form submission.
                         */
                        header(
                            'Location: ../index.php?subscription=success'
                        );
                        exit;

                    } catch (Throwable $e) {

                        $conn->rollback();

                        $message = 'Unable to create the subscription. Please try again.';
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE AFTER REDIRECT
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['subscription']) &&
    $_GET['subscription'] === 'success'
) {
    $message = 'Your subscription has been created successfully!';
    $message_type = 'success';
}

/*
|--------------------------------------------------------------------------
| LOAD ACTIVE PLANS
|--------------------------------------------------------------------------
*/
$plans = [];

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        duration_days,
        total_meals,
        price,
        status
    FROM subscription_plans
    WHERE status = 'active'
    ORDER BY price ASC, duration_days ASC
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }

    $stmt->close();
} else {
    $message = 'Unable to load subscription plans.';
    $message_type = 'danger';
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<style>
    .plans-page {
        background: #f8fafc;
        min-height: 100vh;
        padding-bottom: 70px;
    }

    .plans-hero {
        background:
            linear-gradient(
                135deg,
                rgba(255, 107, 0, 0.96),
                rgba(245, 158, 11, 0.92)
            );
        border-radius: 0 0 35px 35px;
        color: #fff;
        padding: 55px 20px;
        margin-bottom: 45px;
    }

    .plans-hero h1 {
        font-weight: 800;
        font-size: clamp(2rem, 5vw, 3.2rem);
        margin-bottom: 12px;
    }

    .plans-hero p {
        max-width: 650px;
        margin: auto;
        font-size: 1.05rem;
        opacity: .95;
    }

    .plan-card {
        background: #fff;
        border: 1px solid #f1f1f1;
        border-radius: 24px;
        height: 100%;
        overflow: hidden;
        transition: all .25s ease;
        box-shadow: 0 10px 30px rgba(17, 24, 39, .07);
    }

    .plan-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 18px 40px rgba(17, 24, 39, .12);
    }

    .plan-card.featured {
        border: 2px solid #ff6b00;
    }

    .plan-top {
        padding: 28px 25px 20px;
    }

    .plan-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: rgba(255, 107, 0, .1);
        color: #ff6b00;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        margin-bottom: 18px;
    }

    .plan-name {
        color: #111827;
        font-weight: 750;
        font-size: 1.35rem;
        margin-bottom: 8px;
    }

    .plan-description {
        color: #6b7280;
        min-height: 55px;
        font-size: .94rem;
    }

    .plan-price {
        padding: 20px 25px;
        background: #fff7ed;
        border-top: 1px solid #ffedd5;
        border-bottom: 1px solid #ffedd5;
    }

    .plan-price strong {
        color: #ff6b00;
        font-size: 2rem;
        font-weight: 800;
    }

    .plan-price span {
        color: #6b7280;
        font-size: .9rem;
    }

    .plan-details {
        padding: 22px 25px;
    }

    .plan-detail {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 13px;
        color: #374151;
        font-size: .94rem;
    }

    .plan-detail i {
        color: #ff6b00;
        font-size: 1rem;
    }

    .choose-btn {
        width: 100%;
        border-radius: 13px;
        padding: 12px 18px;
        font-weight: 700;
        border: none;
        background: #ff6b00;
        color: #fff;
        transition: .2s ease;
    }

    .choose-btn:hover {
        background: #e85f00;
        color: #fff;
        transform: translateY(-1px);
    }

    .section-title {
        color: #111827;
        font-weight: 800;
    }

    .empty-plans {
        background: #fff;
        border-radius: 22px;
        padding: 55px 25px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(17, 24, 39, .06);
    }

    .empty-plans i {
        font-size: 3rem;
        color: #ff6b00;
        margin-bottom: 15px;
    }
</style>

<div class="plans-page">

    <!-- HERO -->
    <section class="plans-hero text-center">

        <div class="container">

            <div class="mb-3">
                <i class="bi bi-stars fs-2"></i>
            </div>

            <h1>Choose Your Tiffin Plan</h1>

            <p>
                Fresh homemade meals, reliable delivery and flexible plans
                designed around your everyday routine.
            </p>

        </div>

    </section>

    <main class="container">

        <?php if ($message !== ''): ?>

            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show shadow-sm"
                 role="alert">

                <i class="bi
                    <?php echo $message_type === 'success'
                        ? 'bi-check-circle-fill'
                        : 'bi-exclamation-triangle-fill';
                    ?> me-2"></i>

                <?php echo htmlspecialchars($message); ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- SECTION HEADER -->
        <div class="text-center mb-5">

            <span class="badge rounded-pill text-bg-warning px-3 py-2 mb-3">
                <i class="bi bi-box-seam me-1"></i>
                Smart Tiffin Plans
            </span>

            <h2 class="section-title">
                Pick the plan that fits you
            </h2>

            <p class="text-muted mb-0">
                Simple pricing. Fresh meals. No complicated process.
            </p>

        </div>


        <?php if (empty($plans)): ?>

            <!-- EMPTY STATE -->
            <div class="empty-plans">

                <i class="bi bi-calendar-x"></i>

                <h4 class="fw-bold">
                    No plans available right now
                </h4>

                <p class="text-muted mb-0">
                    Our team is preparing subscription plans for you.
                    Please check back soon.
                </p>

            </div>

        <?php else: ?>

            <div class="row g-4 justify-content-center">

                <?php foreach ($plans as $index => $plan): ?>

                    <?php
                    $plan_id = (int)$plan['id'];

                    $plan_name = htmlspecialchars(
                        $plan['name'] ?? 'Tiffin Plan'
                    );

                    $description = htmlspecialchars(
                        $plan['description'] ?? 'Fresh and delicious tiffin meals.'
                    );

                    $duration_days = (int)$plan['duration_days'];
                    $total_meals = (int)$plan['total_meals'];

                    $price = is_numeric($plan['price'])
                        ? number_format((float)$plan['price'], 2)
                        : '0.00';

                    $is_featured = ($index === 1);
                    ?>

                    <div class="col-lg-4 col-md-6">

                        <div class="plan-card <?php echo $is_featured ? 'featured' : ''; ?>">

                            <?php if ($is_featured): ?>

                                <div class="text-center pt-3">

                                    <span class="badge rounded-pill bg-warning text-dark px-3 py-2">
                                        <i class="bi bi-star-fill me-1"></i>
                                        Popular Choice
                                    </span>

                                </div>

                            <?php endif; ?>


                            <div class="plan-top">

                                <div class="plan-icon">
                                    <i class="bi bi-box-seam"></i>
                                </div>

                                <h3 class="plan-name">
                                    <?php echo $plan_name; ?>
                                </h3>

                                <p class="plan-description mb-0">
                                    <?php echo $description; ?>
                                </p>

                            </div>


                            <div class="plan-price">

                                <strong>
                                    ₹<?php echo $price; ?>
                                </strong>

                                <span>
                                    / <?php echo $duration_days; ?> days
                                </span>

                            </div>


                            <div class="plan-details">

                                <div class="plan-detail">

                                    <i class="bi bi-check-circle-fill"></i>

                                    <span>
                                        <?php echo $total_meals; ?> tiffin meals
                                    </span>

                                </div>

                                <div class="plan-detail">

                                    <i class="bi bi-calendar-check"></i>

                                    <span>
                                        <?php echo $duration_days; ?> days validity
                                    </span>

                                </div>

                                <div class="plan-detail">

                                    <i class="bi bi-house-heart-fill"></i>

                                    <span>
                                        Convenient home delivery
                                    </span>

                                </div>


                                <form method="POST"
                                      action=""
                                      onsubmit="return confirm('Start this subscription plan?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <input type="hidden"
                                           name="plan_id"
                                           value="<?php echo $plan_id; ?>">

                                    <button type="submit"
                                            class="choose-btn">

                                        <i class="bi bi-arrow-right-circle me-1"></i>
                                        Choose Plan

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- INFORMATION -->
        <div class="row justify-content-center mt-5">

            <div class="col-lg-9">

                <div class="bg-white rounded-4 shadow-sm p-4 text-center">

                    <div class="d-flex justify-content-center align-items-center gap-2 mb-2">

                        <i class="bi bi-shield-check text-success fs-4"></i>

                        <h5 class="fw-bold mb-0">
                            Fresh meals, simple subscription
                        </h5>

                    </div>

                    <p class="text-muted mb-0">
                        Once you choose a plan, your subscription is stored
                        securely in your Smart Tiffin account.
                    </p>

                </div>

            </div>

        </div>

    </main>

</div>

<?php
require_once __DIR__ . '/../includes/customer_footer.php';
?>