<?php

require_once __DIR__ . '/includes/dabbawala_auth.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'GPS Tracking';
$active_page = 'gps';

$dabbawala_id = (int) $dabbawala_id;

$assignments = [];

$sql = "
    SELECT
        da.id AS assignment_id,
        da.order_id,
        da.assignment_status,
        o.order_number,
        o.delivery_date,
        o.delivery_time
    FROM delivery_assignments da
    INNER JOIN orders o
        ON o.id = da.order_id
    WHERE da.dabbawala_id = ?
      AND da.assignment_status IN (
          'assigned',
          'accepted',
          'picked_up',
          'out_for_delivery',
          'near_customer'
      )
    ORDER BY o.delivery_date ASC, o.delivery_time ASC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $dabbawala_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/includes/dabbawala_header.php';
?>

<div class="container-fluid py-4">

    <!-- PAGE HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-geo-alt-fill text-warning"></i>
                GPS Tracking
            </h2>

            <p class="text-muted mb-0">
                Share your live delivery location with Smart Tiffin.
            </p>
        </div>

        <span id="gpsStatusBadge" class="badge bg-secondary px-3 py-2">
            <i class="bi bi-geo-alt"></i>
            GPS Inactive
        </span>

    </div>


    <?php if (empty($assignments)): ?>

        <!-- EMPTY STATE -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">

                <div class="mb-3">
                    <i class="bi bi-geo-alt fs-1 text-muted"></i>
                </div>

                <h4 class="fw-bold">
                    No Active Deliveries
                </h4>

                <p class="text-muted mb-0">
                    GPS tracking will become available when a delivery is assigned to you.
                </p>

            </div>
        </div>

    <?php else: ?>

        <div class="row g-4">

            <!-- LEFT CONTROL PANEL -->
            <div class="col-lg-4">

                <div class="card border-0 shadow-sm rounded-4 h-100">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-truck text-warning"></i>
                            Select Delivery
                        </h5>

                        <label for="assignmentSelect" class="form-label fw-semibold">
                            Active Delivery
                        </label>

                        <select
                            id="assignmentSelect"
                            class="form-select form-select-lg mb-4"
                        >

                            <option value="">
                                -- Select Delivery --
                            </option>

                            <?php foreach ($assignments as $assignment): ?>

                                <option
                                    value="<?= (int) $assignment['assignment_id'] ?>"
                                >
                                    <?= htmlspecialchars($assignment['order_number']) ?>
                                    -
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $assignment['assignment_status']))) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>


                        <!-- DELIVERY INFO -->
                        <div
                            id="deliveryInfo"
                            class="alert alert-light border rounded-3 d-none"
                        >
                            <div class="small text-muted">
                                Selected Order
                            </div>

                            <div
                                id="selectedOrder"
                                class="fw-bold"
                            >
                                -
                            </div>

                            <div class="mt-2 small">
                                Status:
                                <strong id="selectedStatus">-</strong>
                            </div>
                        </div>


                        <!-- GPS INFORMATION -->

                        <div class="border rounded-4 p-3 mb-3">

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">
                                    <i class="bi bi-pin-map"></i>
                                    Latitude
                                </span>

                                <strong id="latitude">
                                    --
                                </strong>
                            </div>


                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">
                                    <i class="bi bi-pin-map"></i>
                                    Longitude
                                </span>

                                <strong id="longitude">
                                    --
                                </strong>
                            </div>


                            <div class="d-flex justify-content-between">
                                <span class="text-muted">
                                    <i class="bi bi-clock"></i>
                                    Last Updated
                                </span>

                                <strong id="lastUpdated">
                                    --
                                </strong>
                            </div>

                        </div>


                        <!-- BUTTONS -->

                        <div class="d-grid gap-2">

                            <button
                                type="button"
                                id="startTrackingBtn"
                                class="btn btn-warning btn-lg rounded-3"
                            >
                                <i class="bi bi-geo-alt-fill"></i>
                                Start Tracking
                            </button>

                            <button
                                type="button"
                                id="stopTrackingBtn"
                                class="btn btn-outline-danger btn-lg rounded-3"
                                disabled
                            >
                                <i class="bi bi-stop-circle"></i>
                                Stop Tracking
                            </button>

                        </div>


                        <div
                            id="gpsMessage"
                            class="small text-muted mt-3"
                        >
                            Select a delivery and start GPS tracking.
                        </div>

                    </div>
                </div>

            </div>


            <!-- MAP -->
            <div class="col-lg-8">

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">

                    <div
                        id="map"
                        style="height: 600px; min-height: 400px;"
                    ></div>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>


<!-- LEAFLET CSS -->
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>


<!-- LEAFLET JS -->
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
</script>


<script>

const csrfToken = <?= json_encode($csrf_token) ?>;

const assignments = <?= json_encode($assignments) ?>;

let selectedAssignmentId = null;
let watchId = null;
let trackingActive = false;
let marker = null;
let accuracyCircle = null;


/*
|--------------------------------------------------------------------------
| MAP INITIALIZATION
|--------------------------------------------------------------------------
*/

const map = L.map('map').setView(
    [20.5937, 78.9629],
    5
);


L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }
).addTo(map);


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const assignmentSelect =
    document.getElementById('assignmentSelect');

const startTrackingBtn =
    document.getElementById('startTrackingBtn');

const stopTrackingBtn =
    document.getElementById('stopTrackingBtn');

const gpsStatusBadge =
    document.getElementById('gpsStatusBadge');

const gpsMessage =
    document.getElementById('gpsMessage');

const latitudeElement =
    document.getElementById('latitude');

const longitudeElement =
    document.getElementById('longitude');

const lastUpdatedElement =
    document.getElementById('lastUpdated');

const deliveryInfo =
    document.getElementById('deliveryInfo');

const selectedOrder =
    document.getElementById('selectedOrder');

const selectedStatus =
    document.getElementById('selectedStatus');


/*
|--------------------------------------------------------------------------
| SELECT DELIVERY
|--------------------------------------------------------------------------
*/

assignmentSelect?.addEventListener('change', function () {

    selectedAssignmentId = this.value
        ? parseInt(this.value)
        : null;

    if (!selectedAssignmentId) {

        deliveryInfo.classList.add('d-none');

        gpsMessage.textContent =
            'Select a delivery and start GPS tracking.';

        return;
    }

    const selected =
        assignments.find(
            item =>
                parseInt(item.assignment_id) === selectedAssignmentId
        );

    if (selected) {

        deliveryInfo.classList.remove('d-none');

        selectedOrder.textContent =
            selected.order_number;

        selectedStatus.textContent =
            selected.assignment_status
                .replaceAll('_', ' ')
                .replace(/\b\w/g, letter => letter.toUpperCase());

        gpsMessage.textContent =
            'Delivery selected. Click Start Tracking to begin GPS updates.';
    }

});


/*
|--------------------------------------------------------------------------
| START TRACKING
|--------------------------------------------------------------------------
*/

startTrackingBtn?.addEventListener('click', function () {

    if (!selectedAssignmentId) {

        alert('Please select a delivery first.');

        return;
    }


    if (!navigator.geolocation) {

        gpsMessage.textContent =
            'Geolocation is not supported by this browser.';

        gpsStatusBadge.className =
            'badge bg-danger px-3 py-2';

        gpsStatusBadge.innerHTML =
            '<i class="bi bi-exclamation-circle"></i> GPS Unsupported';

        return;
    }


    if (trackingActive) {
        return;
    }


    trackingActive = true;

    startTrackingBtn.disabled = true;
    stopTrackingBtn.disabled = false;

    gpsStatusBadge.className =
        'badge bg-success px-3 py-2';

    gpsStatusBadge.innerHTML =
        '<i class="bi bi-broadcast-pin"></i> GPS Active';

    gpsMessage.textContent =
        'Requesting your current location...';


    watchId = navigator.geolocation.watchPosition(
        handlePosition,
        handleLocationError,
        {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 15000
        }
    );

});


/*
|--------------------------------------------------------------------------
| STOP TRACKING
|--------------------------------------------------------------------------
*/

stopTrackingBtn?.addEventListener('click', function () {

    stopTracking();

});


function stopTracking()
{

    if (watchId !== null) {

        navigator.geolocation.clearWatch(watchId);

        watchId = null;
    }

    trackingActive = false;

    startTrackingBtn.disabled = false;
    stopTrackingBtn.disabled = true;

    gpsStatusBadge.className =
        'badge bg-secondary px-3 py-2';

    gpsStatusBadge.innerHTML =
        '<i class="bi bi-geo-alt"></i> GPS Inactive';

    gpsMessage.textContent =
        'GPS tracking stopped.';
}


/*
|--------------------------------------------------------------------------
| LOCATION SUCCESS
|--------------------------------------------------------------------------
*/

function handlePosition(position)
{

    if (!selectedAssignmentId) {
        return;
    }


    const latitude =
        position.coords.latitude;

    const longitude =
        position.coords.longitude;

    const accuracy =
        position.coords.accuracy;


    latitudeElement.textContent =
        latitude.toFixed(6);

    longitudeElement.textContent =
        longitude.toFixed(6);

    lastUpdatedElement.textContent =
        new Date().toLocaleTimeString();


    /*
    |--------------------------------------------------------------------------
    | MAP MARKER
    |--------------------------------------------------------------------------
    */

    if (!marker) {

        marker = L.marker(
            [latitude, longitude]
        ).addTo(map);

        marker.bindPopup(
            '<strong>Dabbawala Location</strong>'
        );

    } else {

        marker.setLatLng(
            [latitude, longitude]
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ACCURACY CIRCLE
    |--------------------------------------------------------------------------
    */

    if (!accuracyCircle) {

        accuracyCircle = L.circle(
            [latitude, longitude],
            {
                radius: accuracy
            }
        ).addTo(map);

    } else {

        accuracyCircle.setLatLng(
            [latitude, longitude]
        );

        accuracyCircle.setRadius(
            accuracy
        );

    }


    map.setView(
        [latitude, longitude],
        16
    );


    /*
    |--------------------------------------------------------------------------
    | SEND GPS TO SERVER
    |--------------------------------------------------------------------------
    */

    sendGPS(
        latitude,
        longitude
    );

}


/*
|--------------------------------------------------------------------------
| SEND GPS
|--------------------------------------------------------------------------
*/

async function sendGPS(
    latitude,
    longitude
)
{

    try {

        const formData =
            new FormData();

        formData.append(
            'csrf_token',
            csrfToken
        );

        formData.append(
            'assignment_id',
            selectedAssignmentId
        );

        formData.append(
            'latitude',
            latitude
        );

        formData.append(
            'longitude',
            longitude
        );


        const response =
            await fetch(
                'update_gps.php',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            gpsMessage.textContent =
                data.message || 'GPS update failed.';

            return;
        }


        gpsMessage.textContent =
            'Location updated successfully.';


        if (data.recorded_at) {

            lastUpdatedElement.textContent =
                data.recorded_at;
        }

    }
    catch (error) {

        console.error(error);

        gpsMessage.textContent =
            'Unable to send GPS location to server.';
    }

}


/*
|--------------------------------------------------------------------------
| LOCATION ERROR
|--------------------------------------------------------------------------
*/

function handleLocationError(error)
{

    let message =
        'Unable to get your location.';


    switch (error.code) {

        case error.PERMISSION_DENIED:

            message =
                'Location permission was denied. Please allow location access.';

            break;


        case error.POSITION_UNAVAILABLE:

            message =
                'Your current location is unavailable.';

            break;


        case error.TIMEOUT:

            message =
                'Location request timed out.';

            break;
    }


    gpsMessage.textContent =
        message;


    gpsStatusBadge.className =
        'badge bg-danger px-3 py-2';

    gpsStatusBadge.innerHTML =
        '<i class="bi bi-exclamation-circle"></i> GPS Error';
}


/*
|--------------------------------------------------------------------------
| CLEANUP
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'beforeunload',
    function () {

        if (watchId !== null) {

            navigator.geolocation.clearWatch(
                watchId
            );
        }

    }
);

</script>


<?php
require_once __DIR__ . '/includes/dabbawala_footer.php';
?>