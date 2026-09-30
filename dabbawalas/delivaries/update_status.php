<?php

require_once __DIR__ . '/../includes/dabbawala_auth.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF CHECK
|--------------------------------------------------------------------------
*/

if (function_exists('verify_csrf_token')) {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        header('Location: index.php?error=invalid_csrf');
        exit;
    }
}

$assignment_id = filter_input(
    INPUT_POST,
    'assignment_id',
    FILTER_VALIDATE_INT
);

$new_status = strtolower(
    trim($_POST['status'] ?? '')
);

$remarks = trim(
    $_POST['remarks'] ?? ''
);

if (!$assignment_id || $assignment_id <= 0) {
    header('Location: index.php?error=invalid_assignment');
    exit;
}

/*
|--------------------------------------------------------------------------
| Allowed statuses
|--------------------------------------------------------------------------
*/

$allowed_statuses = [
    'assigned',
    'accepted',
    'picked_up',
    'out_for_delivery',
    'near_customer',
    'delivered',
    'failed',
    'cancelled'
];

if (!in_array($new_status, $allowed_statuses, true)) {

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=invalid_status'
    );
    exit;
}

if (strlen($remarks) > 500) {
    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=remarks_too_long'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Get current assignment
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        order_id,
        dabbawala_id,
        assignment_status
    FROM delivery_assignments
    WHERE id = ?
      AND dabbawala_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header('Location: index.php?error=database');
    exit;
}

$stmt->bind_param(
    'ii',
    $assignment_id,
    $dabbawala_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header(
        'Location: index.php?error=unauthorized'
    );
    exit;
}

$assignment = $result->fetch_assoc();

$stmt->close();

$current_status = strtolower(
    (string) $assignment['assignment_status']
);

/*
|--------------------------------------------------------------------------
| Rejected assignments cannot continue
|--------------------------------------------------------------------------
*/

if ($current_status === 'rejected') {

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=assignment_rejected'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Status transition rules
|--------------------------------------------------------------------------
|
| assigned
|    ↓
| accepted
|    ↓
| picked_up
|    ↓
| out_for_delivery
|    ↓
| near_customer
|    ↓
| delivered
|
| failed/cancelled can be used only before delivered.
|
*/

$allowed_transitions = [

    'assigned' => [
        'accepted',
        'cancelled',
        'failed'
    ],

    'accepted' => [
        'picked_up',
        'cancelled',
        'failed'
    ],

    'picked_up' => [
        'out_for_delivery',
        'cancelled',
        'failed'
    ],

    'out_for_delivery' => [
        'near_customer',
        'delivered',
        'failed',
        'cancelled'
    ],

    'near_customer' => [
        'delivered',
        'failed',
        'cancelled'
    ],

    'delivered' => [],

    'failed' => [],

    'cancelled' => []

];

/*
|--------------------------------------------------------------------------
| Normalize assignment status
|--------------------------------------------------------------------------
*/

if ($current_status === 'pending') {
    $current_status = 'assigned';
}

if (
    !isset($allowed_transitions[$current_status]) ||
    !in_array(
        $new_status,
        $allowed_transitions[$current_status],
        true
    )
) {

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=invalid_transition'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Prevent direct status skipping
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Update assignment status where applicable
    |--------------------------------------------------------------------------
    */

    $assignment_status = $new_status;

    if ($new_status === 'delivered') {
        $assignment_status = 'completed';
    }

    $update_sql = "
        UPDATE delivery_assignments
        SET
            assignment_status = ?,
            updated_at = NOW()
    ";

    /*
    |--------------------------------------------------------------------------
    | Set accepted_at
    |--------------------------------------------------------------------------
    */

    if ($new_status === 'accepted') {
        $update_sql .= ",
            accepted_at = COALESCE(accepted_at, NOW())
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | Set completed_at
    |--------------------------------------------------------------------------
    */

    if ($new_status === 'delivered') {
        $update_sql .= ",
            completed_at = NOW()
        ";
    }

    $update_sql .= "
        WHERE id = ?
          AND dabbawala_id = ?
    ";

    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {
        throw new Exception(
            'Unable to prepare assignment update.'
        );
    }

    $update_stmt->bind_param(
        'sii',
        $assignment_status,
        $assignment_id,
        $dabbawala_id
    );

    if (!$update_stmt->execute()) {
        throw new Exception(
            'Unable to update assignment.'
        );
    }

    $update_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Insert tracking record
    |--------------------------------------------------------------------------
    */

    $tracking_sql = "
        INSERT INTO delivery_tracking
        (
            assignment_id,
            status,
            updated_by,
            remarks,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ";

    $tracking_stmt = $conn->prepare($tracking_sql);

    if (!$tracking_stmt) {
        throw new Exception(
            'Unable to prepare tracking update.'
        );
    }

    $tracking_stmt->bind_param(
        'isis',
        $assignment_id,
        $new_status,
        $dabbawala_id,
        $remarks
    );

    if (!$tracking_stmt->execute()) {
        throw new Exception(
            'Unable to save delivery tracking.'
        );
    }

    $tracking_stmt->close();

    $conn->commit();

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&success=status_updated'
    );
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=status_update_failed'
    );
    exit;
}