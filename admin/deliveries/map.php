<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';


/*
 * ==========================================================
 * AJAX LOCATION REQUEST
 * ==========================================================
 */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'locations'
) {

    header('Content-Type: application/json; charset=utf-8');

    $locations = [];

    /*
     * Latest GPS record for each active assignment.
     */
    $stmt = $conn->prepare("
        SELECT
            da.id AS assignment_id,
            o.order_number,
            u.name AS customer_name,
            d.name AS dabbawala_name,
            dt.status AS delivery_status,
            gps.latitude,
            gps.longitude,
            gps.recorded_at

        FROM delivery_assignments da

        INNER JOIN orders o
            ON da.order_id = o.id

        INNER JOIN users u
            ON o.user_id = u.id

        INNER JOIN dabbawalas d
            ON da.dabbawala_id = d.id

        LEFT JOIN (
            SELECT
                g1.assignment_id,
                g1.latitude,
                g1.longitude,
                g1.recorded_at
            FROM gps_locations g1
            INNER JOIN (
                SELECT
                    assignment_id,
                    MAX(id) AS latest_id
                FROM gps_locations
                GROUP BY assignment_id
            ) latest
                ON g1.assignment_id = latest.assignment_id
                AND g1.id = latest.latest_id
        ) gps
            ON gps.assignment_id = da.id

        LEFT JOIN (
            SELECT
                t1.assignment_id,
                t1.status
            FROM delivery_tracking t1
            INNER JOIN (
                SELECT
                    assignment_id,
                    MAX(id) AS latest_id
                FROM delivery_tracking
                GROUP BY assignment_id
            ) latest_tracking
                ON t1.assignment_id = latest_tracking.assignment_id
                AND t1.id = latest_tracking.latest_id
        ) dt
            ON dt.assignment_id = da.id

        WHERE da.assignment_status IN ('assigned', 'accepted')

        ORDER BY da.updated_at DESC
    ");

    if (!$stmt) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to load delivery locations.'
        ]);

        exit;
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $locations[] = [
            'assignment_id' => (int)$row['assignment_id'],
            'order_number' => $row['order_number'],
            'customer_name' => $row['customer_name'],
            'dabbawala_name' => $row['dabbawala_name'],
            'delivery_status' => $row['delivery_status'] ?: 'assigned',
            'latitude' =>
                $row['latitude'] !== null
                    ? (float)$row['latitude']
                    : null,
            'longitude' =>
                $row['longitude'] !== null
                    ? (float)$row['longitude']
                    : null,
            'recorded_at' => $row['recorded_at']
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'locations' => $locations
    ]);

    exit;
}


/*
 * ==========================================================
 * NORMAL ADMIN PAGE
 * ==========================================================
 */

$page_title = 'Live GPS Tracking';
$active_page = 'deliveries';

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-page-header">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <h2>
                Live GPS Tracking
            </h2>

            <p class="text-muted mb-0">
                Monitor active dabbawala locations.
            </p>

        </div>

        <span
            id="refreshStatus"
            class="badge bg-light text-dark"
        >
            Updating...
        </span>

    </div>

</div>


<div class="row g-4">

    <div class="col-12 col-xl-8">

        <div class="admin-data-card">

            <div class="card-body p-0">

                <div
                    id="trackingMap"
                    style="
                        width:100%;
                        height:600px;
                        border-radius:12px;
                    "
                ></div>

            </div>

        </div>

    </div>


    <div class="col-12 col-xl-4">

        <div class="admin-data-card">

            <div class="admin-data-card-header">

                <h5 class="mb-1">
                    Active Deliveries
                </h5>

                <small class="text-muted">
                    Latest GPS information
                </small>

            </div>

            <div
                id="deliveryList"
                class="card-body"
            >

                <div class="text-center py-4 text-muted">

                    <div
                        class="spinner-border spinner-border-sm mb-2"
                    ></div>

                    <div>
                        Loading deliveries...
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- Leaflet -->

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>


<script>

(function () {

    const map = L.map('trackingMap').setView(
        [20.5937, 78.9629],
        5
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);


    const markers = new Map();

    let firstLocationLoaded = false;


    function escapeHtml(value)
    {
        const div = document.createElement('div');

        div.textContent =
            value === null ||
            value === undefined
                ? ''
                : String(value);

        return div.innerHTML;
    }


    function statusLabel(status)
    {
        return String(status || 'assigned')
            .replaceAll('_', ' ')
            .replace(
                /\b\w/g,
                function (character) {
                    return character.toUpperCase();
                }
            );
    }


    function statusClass(status)
    {
        switch (status) {

            case 'delivered':
                return 'bg-success';

            case 'failed':
            case 'cancelled':
                return 'bg-danger';

            case 'near_customer':
                return 'bg-warning text-dark';

            case 'out_for_delivery':
                return 'bg-primary';

            default:
                return 'bg-secondary';
        }
    }


    function updateMarker(item)
    {
        /*
         * No marker is created if GPS coordinates do not exist.
         */
        if (
            item.latitude === null ||
            item.longitude === null
        ) {
            return;
        }


        const position = [
            Number(item.latitude),
            Number(item.longitude)
        ];


        let marker = markers.get(
            item.assignment_id
        );


        const popupHtml = `
            <div>
                <strong>
                    ${escapeHtml(item.dabbawala_name)}
                </strong>

                <br>

                Order:
                ${escapeHtml(item.order_number)}

                <br>

                Customer:
                ${escapeHtml(item.customer_name)}

                <br>

                Status:
                ${escapeHtml(statusLabel(item.delivery_status))}

                <br>

                Last updated:
                ${escapeHtml(item.recorded_at || '—')}
            </div>
        `;


        if (!marker) {

            marker = L.marker(position)
                .addTo(map);

            markers.set(
                item.assignment_id,
                marker
            );

        } else {

            marker.setLatLng(position);
        }


        marker.bindPopup(popupHtml);
    }


    function updateList(locations)
    {
        const list =
            document.getElementById(
                'deliveryList'
            );

        if (!locations.length) {

            list.innerHTML = `
                <div class="text-center py-5">

                    <i class="bi bi-truck fs-1 text-muted"></i>

                    <h6 class="fw-bold mt-3">
                        No active deliveries
                    </h6>

                    <p class="text-muted small mb-0">
                        Active delivery assignments will appear here.
                    </p>

                </div>
            `;

            return;
        }


        let html = '';


        locations.forEach(function (item) {

            const hasGps =
                item.latitude !== null &&
                item.longitude !== null;


            html += `
                <div class="border-bottom py-3">

                    <div class="d-flex justify-content-between gap-2">

                        <div>

                            <div class="fw-semibold">
                                ${escapeHtml(item.order_number)}
                            </div>

                            <div class="small text-muted">
                                ${escapeHtml(item.customer_name)}
                            </div>

                            <div class="small mt-1">
                                Dabbawala:
                                ${escapeHtml(item.dabbawala_name)}
                            </div>

                        </div>

                        <span class="badge ${statusClass(item.delivery_status)}">
                            ${escapeHtml(statusLabel(item.delivery_status))}
                        </span>

                    </div>

                    <div class="small mt-2">

                        ${
                            hasGps
                                ? `
                                    <div>
                                        <i class="bi bi-geo-alt me-1"></i>
                                        ${Number(item.latitude).toFixed(6)},
                                        ${Number(item.longitude).toFixed(6)}
                                    </div>

                                    <div class="text-muted">
                                        Updated:
                                        ${escapeHtml(item.recorded_at)}
                                    </div>
                                  `
                                : `
                                    <span class="text-muted">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        GPS location unavailable
                                    </span>
                                  `
                        }

                    </div>

                </div>
            `;
        });


        list.innerHTML = html;
    }


    async function loadLocations()
    {
        try {

            const response = await fetch(
                'map.php?action=locations',
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest'
                    }
                }
            );


            const data =
                await response.json();


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'Unable to load locations.'
                );
            }


            const locations =
                Array.isArray(data.locations)
                    ? data.locations
                    : [];


            locations.forEach(
                updateMarker
            );


            updateList(locations);


            if (!firstLocationLoaded) {

                const gpsLocations =
                    locations.filter(
                        function (item) {

                            return (
                                item.latitude !== null &&
                                item.longitude !== null
                            );
                        }
                    );


                if (gpsLocations.length) {

                    const bounds =
                        L.latLngBounds(
                            gpsLocations.map(
                                function (item) {
                                    return [
                                        item.latitude,
                                        item.longitude
                                    ];
                                }
                            )
                        );

                    map.fitBounds(
                        bounds,
                        {
                            padding: [30, 30]
                        }
                    );

                    firstLocationLoaded = true;
                }
            }


            document.getElementById(
                'refreshStatus'
            ).textContent =
                'Updated ' +
                new Date().toLocaleTimeString();


        } catch (error) {

            document.getElementById(
                'refreshStatus'
            ).textContent =
                'Update failed';

            document.getElementById(
                'refreshStatus'
            ).className =
                'badge bg-danger';
        }
    }


    loadLocations();

    /*
     * Poll every 5 seconds.
     * The complete page is NOT reloaded.
     */
    setInterval(
        loadLocations,
        5000
    );

})();

</script>


<?php

require_once __DIR__ . '/../includes/admin_footer.php';

?>