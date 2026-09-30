<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/dabbawala_auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| ONLY POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF VALIDATION
|--------------------------------------------------------------------------
*/

$csrf_token =
    $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrf_token
    )
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGGED-IN DABBAWALA
|--------------------------------------------------------------------------
*/

$dabbawala_id =
    (int) $dabbawala_id;

if ($dabbawala_id <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET POST DATA
|--------------------------------------------------------------------------
*/

$assignment_id =
    filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

$latitude =
    filter_input(
        INPUT_POST,
        'latitude',
        FILTER_VALIDATE_FLOAT
    );

$longitude =
    filter_input(
        INPUT_POST,
        'longitude',
        FILTER_VALIDATE_FLOAT
    );


/*
|--------------------------------------------------------------------------
| VALIDATE ASSIGNMENT ID
|--------------------------------------------------------------------------
*/

if (
    $assignment_id === false ||
    $assignment_id === null ||
    $assignment_id <= 0
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid delivery assignment.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE LATITUDE
|--------------------------------------------------------------------------
*/

if (
    $latitude === false ||
    $latitude === null ||
    !is_numeric($latitude) ||
    $latitude < -90 ||
    $latitude > 90
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid latitude.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE LONGITUDE
|--------------------------------------------------------------------------
*/

if (
    $longitude === false ||
    $longitude === null ||
    !is_numeric($longitude) ||
    $longitude < -180 ||
    $longitude > 180
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid longitude.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFY ASSIGNMENT BELONGS TO DABBAWALA
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        assignment_status
    FROM delivery_assignments
    WHERE id = ?
      AND dabbawala_id = ?
    LIMIT 1
";

$stmt =
    $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.'
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $assignment_id,
    $dabbawala_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$assignment =
    $result->fetch_assoc();

$stmt->close();


if (!$assignment) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to update this delivery.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ONLY ACTIVE DELIVERIES CAN SEND GPS
|--------------------------------------------------------------------------
*/

$allowed_statuses = [
    'assigned',
    'accepted',
    'picked_up',
    'out_for_delivery',
    'near_customer'
];


if (
    !in_array(
        $assignment['assignment_status'],
        $allowed_statuses,
        true
    )
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'GPS tracking is not active for this delivery.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| INSERT GPS LOCATION
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO gps_locations
    (
        assignment_id,
        dabbawala_id,
        latitude,
        longitude,
        recorded_at
    )
    VALUES
    (?, ?, ?, ?, NOW())
";

$stmt =
    $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare GPS query.'
    ]);

    exit;
}


$latitude =
    (float) $latitude;

$longitude =
    (float) $longitude;


$stmt->bind_param(
    "iidd",
    $assignment_id,
    $dabbawala_id,
    $latitude,
    $longitude
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save GPS location.'
    ]);

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'GPS location updated successfully.',
    'latitude' => $latitude,
    'longitude' => $longitude,
    'recorded_at' => date('Y-m-d H:i:s')
]);

exit;