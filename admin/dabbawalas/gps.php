<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'GPS Tracking';
$active_page = 'gps';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
 * The existing dabbawala login should provide the logged-in
 * dabbawala ID in the session.
 *
 * We support the common existing session keys without allowing
 * the browser to submit a dabbawala ID.
 */
$dabbawala_id = (int)(
    $_SESSION['dabbawala_id']
    ?? $_SESSION['user_id']
    ?? 0
);

$dabbawala_name = 'Dabbawala';
$has_active_delivery = false;

if ($dabbawala_id > 0) {

    /*
     * Verify that the session ID actually belongs to a dabbawala.
     */
    $stmt = $conn->prepare("
        SELECT id, name
        FROM dabbawalas
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param('i', $dabbawala_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $dabbawala = $result->fetch_assoc();

        if ($dabbawala) {
            $dabbawala_name = $dabbawala['name'];
        } else {
            $dabbawala_id = 0;
        }

        $stmt->close();
    }
}

/*
 * Check whether this dabbawala currently has a delivery.
 */
if ($dabbawala_id > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM delivery_assignments
        WHERE dabbawala_id = ?
        AND assignment_status IN ('assigned', 'accepted')
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param('i', $dabbawala_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->fetch_assoc()) {
            $has_active_delivery = true;
        }

        $stmt->close();
    }
}

require_once __DIR__ . '/../../includes/admin_header.php';

?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">

        <div class="col-12 col-lg-8 col-xl-6">

            <div class="admin-data-card">

                <div class="card-body p-4">

                    <div class="text-center mb-4">

                        <div
                            class="rounded-circle bg-warning-subtle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:80px;height:80px;"
                        >
                            <i class="bi bi-geo-alt-fill fs-1 text-warning"></i>
                        </div>

                        <h3 class="fw-bold mb-1">
                            GPS Location Sharing
                        </h3>

                        <p class="text-muted mb-0">
                            Share your current location while delivering
                            an assigned tiffin.
                        </p>

                    </div>

                    <?php if ($dabbawala_id <= 0): ?>

                        <div class="alert alert-danger">
                            Your dabbawala session could not be verified.
                            Please log in again.
                        </div>

                    <?php elseif (!$has_active_delivery): ?>

                        <div class="alert alert-info">

                            <i class="bi bi-info-circle me-2"></i>

                            No active delivery is currently assigned to you.
                            GPS sharing will become available after a delivery
                            is assigned.

                        </div>

                    <?php else: ?>

                        <div
                            id="gpsStatus"
                            class="alert alert-secondary d-flex align-items-center"
                        >

                            <i
                                id="gpsStatusIcon"
                                class="bi bi-geo-alt me-2"
                            ></i>

                            <span id="gpsStatusText">
                                Location sharing is inactive.
                            </span>

                        </div>

                        <div class="d-grid">

                            <button
                                type="button"
                                id="startGpsBtn"
                                class="btn btn-warning btn-lg"
                            >

                                <i class="bi bi-geo-alt-fill me-2"></i>

                                Start Location Sharing

                            </button>

                        </div>

                        <div class="text-center mt-3">

                            <small class="text-muted">
                                Your browser will ask for location permission.
                                Location updates are sent periodically while
                                sharing is active.
                            </small>

                        </div>

                        <div
                            id="gpsDetails"
                            class="mt-4 d-none"
                        >

                            <div class="row g-3">

                                <div class="col-6">

                                    <div class="bg-light rounded p-3">

                                        <small class="text-muted d-block">
                                            Latitude
                                        </small>

                                        <strong id="latitude">
                                            —
                                        </strong>

                                    </div>

                                </div>

                                <div class="col-6">

                                    <div class="bg-light rounded p-3">

                                        <small class="text-muted d-block">
                                            Longitude
                                        </small>

                                        <strong id="longitude">
                                            —
                                        </strong>

                                    </div>

                                </div>

                            </div>

                            <div class="text-center mt-3">

                                <small class="text-muted">
                                    Last sent:
                                    <span id="lastSent">—</span>
                                </small>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>


<?php if ($dabbawala_id > 0 && $has_active_delivery): ?>

<script>

(function () {

    const startButton = document.getElementById('startGpsBtn');
    const statusBox = document.getElementById('gpsStatus');
    const statusIcon = document.getElementById('gpsStatusIcon');
    const statusText = document.getElementById('gpsStatusText');

    const details = document.getElementById('gpsDetails');
    const latitudeElement = document.getElementById('latitude');
    const longitudeElement = document.getElementById('longitude');
    const lastSentElement = document.getElementById('lastSent');

    let watchId = null;
    let sharingActive = false;

    function setStatus(type, icon, message)
    {
        statusBox.className =
            'alert d-flex align-items-center alert-' + type;

        statusIcon.className = 'bi ' + icon + ' me-2';

        statusText.textContent = message;
    }

    function stopSharing()
    {
        if (watchId !== null) {

            navigator.geolocation.clearWatch(watchId);

            watchId = null;
        }

        sharingActive = false;

        startButton.disabled = false;

        startButton.innerHTML =
            '<i class="bi bi-geo-alt-fill me-2"></i>' +
            'Start Location Sharing';

        setStatus(
            'secondary',
            'bi-geo-alt',
            'Location sharing is inactive.'
        );
    }

    async function sendLocation(position)
    {
        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude) ||
            latitude < -90 ||
            latitude > 90 ||
            longitude < -180 ||
            longitude > 180
        ) {

            setStatus(
                'danger',
                'bi-exclamation-triangle',
                'Invalid GPS coordinates received.'
            );

            return;
        }

        latitudeElement.textContent = latitude.toFixed(8);
        longitudeElement.textContent = longitude.toFixed(8);

        details.classList.remove('d-none');

        try {

            const response = await fetch(
                'api/update_location.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        latitude: latitude,
                        longitude: longitude,
                        timestamp:
                            new Date().toISOString()
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {

                setStatus(
                    'danger',
                    'bi-exclamation-triangle',
                    data.message ||
                    'Unable to save your location.'
                );

                return;
            }

            setStatus(
                'success',
                'bi-geo-alt-fill',
                'Location sharing active.'
            );

            lastSentElement.textContent =
                new Date().toLocaleTimeString();

        } catch (error) {

            setStatus(
                'danger',
                'bi-wifi-off',
                'Unable to communicate with the server.'
            );
        }
    }

    function handleLocationError(error)
    {
        let message =
            'Unable to get your location.';

        if (error.code === 1) {

            message =
                'Location permission was denied. Please allow location access.';

        } else if (error.code === 2) {

            message =
                'Your location is currently unavailable.';

        } else if (error.code === 3) {

            message =
                'Location request timed out.';
        }

        setStatus(
            'danger',
            'bi-exclamation-triangle',
            message
        );
    }

    function startSharing()
    {
        if (!navigator.geolocation) {

            setStatus(
                'danger',
                'bi-x-circle',
                'Geolocation is not supported by this browser.'
            );

            return;
        }

        sharingActive = true;

        startButton.disabled = true;

        startButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Location Sharing Active';

        setStatus(
            'warning',
            'bi-hourglass-split',
            'Requesting your current location...'
        );

        watchId = navigator.geolocation.watchPosition(
            sendLocation,
            handleLocationError,
            {
                enableHighAccuracy: true,
                maximumAge: 10000,
                timeout: 15000
            }
        );
    }

    startButton.addEventListener(
        'click',
        startSharing
    );

})();

</script>

<?php endif; ?>


<?php

require_once __DIR__ . '/../../includes/admin_footer.php';

?>