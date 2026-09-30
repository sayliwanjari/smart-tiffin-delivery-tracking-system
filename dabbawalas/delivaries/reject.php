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

$rejection_reason = trim(
    $_POST['rejection_reason'] ?? ''
);

if (!$assignment_id || $assignment_id <= 0) {
    header('Location: index.php?error=invalid_assignment');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate rejection reason
|--------------------------------------------------------------------------
*/

if ($rejection_reason === '') {
    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=rejection_reason_required'
    );
    exit;
}

if (strlen($rejection_reason) > 500) {
    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=rejection_reason_too_long'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify ownership
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        order_id,
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
| Only assigned deliveries can be rejected
|--------------------------------------------------------------------------
*/

if (
    $current_status !== 'assigned' &&
    $current_status !== 'pending'
) {
    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=cannot_reject'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Update assignment
    |--------------------------------------------------------------------------
    */

    $update_sql = "
        UPDATE delivery_assignments
        SET
            assignment_status = 'rejected',
            rejected_at = NOW(),
            rejection_reason = ?,
            updated_at = NOW()
        WHERE id = ?
          AND dabbawala_id = ?
          AND (
              assignment_status = 'assigned'
              OR assignment_status = 'pending'
          )
    ";

    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {
        throw new Exception('Unable to prepare rejection update.');
    }

    $update_stmt->bind_param(
        'sii',
        $rejection_reason,
        $assignment_id,
        $dabbawala_id
    );

    if (!$update_stmt->execute()) {
        throw new Exception('Unable to reject delivery.');
    }

    if ($update_stmt->affected_rows !== 1) {
        throw new Exception('Delivery could not be rejected.');
    }

    $update_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Add tracking record
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
            'cancelled',
            ?,
            ?,
            NOW()
        )
    ";

    $tracking_stmt = $conn->prepare($tracking_sql);

    if (!$tracking_stmt) {
        throw new Exception('Unable to prepare tracking record.');
    }

    $remarks = 'Delivery rejected: ' . $rejection_reason;

    $tracking_stmt->bind_param(
        'iis',
        $assignment_id,
        $dabbawala_id,
        $remarks
    );

    if (!$tracking_stmt->execute()) {
        throw new Exception('Unable to create tracking record.');
    }

    $tracking_stmt->close();

    $conn->commit();

    header(
        'Location: index.php?success=rejected'
    );
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    header(
        'Location: view.php?id=' .
        $assignment_id .
        '&error=reject_failed'
    );
    exit;
}