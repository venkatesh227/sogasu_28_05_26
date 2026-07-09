<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location:index.php");
    exit();
}

$pageTitle = "Address";
$headerTitle = "Address";
$activePage = "profile";

$userId = $_SESSION['user_id'];

$isEdit = false;
$address = [
    'full_name' => '',
    'phone' => '',
    'address_type' => 'Home',
    'house_no' => '',
    'apartment' => '',
    'landmark' => '',
    'area' => '',
    'city' => '',
    'state' => '',
    'pincode' => '',
    'is_default' => 0
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM customer_addresses
        WHERE id = ?
        AND user_id = ?
        AND is_deleted = 0
    ");

    $stmt->execute([
        $_GET['id'],
        $userId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $address = $row;
        $isEdit = true;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address_type = $_POST['address_type'];
    $house_no = trim($_POST['house_no']);
    $apartment = trim($_POST['apartment']);
    $landmark = trim($_POST['landmark']);
    $area = trim($_POST['area']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if ($is_default == 1) {

        $pdo->prepare("
            UPDATE customer_addresses
            SET is_default = 0
            WHERE user_id = ?
        ")->execute([$userId]);

    }

    if ($isEdit) {

        $stmt = $pdo->prepare("
            UPDATE customer_addresses
            SET
                full_name=?,
                phone=?,
                address_type=?,
                house_no=?,
                apartment=?,
                landmark=?,
                area=?,
                city=?,
                state=?,
                pincode=?,
                latitude=?,
                longitude=?,
                is_default=?,
                updated_at=?
            WHERE id=?
            AND user_id=?
        ");

        $stmt->execute([
            $full_name,
            $phone,
            $address_type,
            $house_no,
            $apartment,
            $landmark,
            $area,
            $city,
            $state,
            $pincode,
            $latitude,
            $longitude,
            $is_default,
            date('Y-m-d H:i:s'),
            $_GET['id'],
            $userId
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO customer_addresses
            (
                user_id,
                full_name,
                phone,
                address_type,
                house_no,
                apartment,
                landmark,
                area,
                city,
                state,
                pincode,
                latitude,
                longitude,
                is_default,
                created_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )
        ");

        $stmt->execute([
            $userId,
            $full_name,
            $phone,
            $address_type,
            $house_no,
            $apartment,
            $landmark,
            $area,
            $city,
            $state,
            $pincode,
            $latitude,
            $longitude,
            $is_default,
            date('Y-m-d H:i:s')
        ]);

    }

    $_SESSION['success_message'] =
        $isEdit
        ? "Address updated successfully."
        : "Address added successfully.";

    header("Location:manage-addresses.php");
    exit();

}

include 'includes/header.php';
?>
<div class="container">

    <div class="card">

        <form method="POST" id="addressForm">

            <div class="section-title">
                <?= $isEdit ? 'Edit Address' : 'Add New Address'; ?>
            </div>
            <label class="input-label">
                Search Location
            </label>

            <div class="location-search">

                <input type="text" id="locationSearch" class="form-input" placeholder="Search your location">

            </div>

            <button type="button" id="currentLocationBtn" class="btn-primary" style="margin-top:12px;width:100%;">

                Use Current Location

            </button>

            <div id="mapWrapper">

                <div id="addressMap"></div>

                <div class="center-pin">
                    <i class="ri-map-pin-fill"></i>
                </div>

            </div>

            <label class="input-label">Full Name</label>
            <input type="text" name="full_name" class="form-input"
                value="<?= htmlspecialchars($address['full_name']) ?>">

            <label class="input-label">Mobile Number</label>
            <input type="tel" name="phone" class="form-input" maxlength="10"
                value="<?= htmlspecialchars($address['phone']) ?>">

            <label class="input-label">Address Type</label>

            <div class="address-type">

                <?php

                $types = ['Home', 'Work', 'Other'];

                foreach ($types as $type):

                    ?>

                    <label class="type-card">

                        <input type="radio" name="address_type" value="<?= $type ?>" <?= $address['address_type'] == $type ? 'checked' : ''; ?>>

                        <?php if ($type == 'Home'): ?>
                            <i class="ri-home-5-line"></i>
                        <?php elseif ($type == 'Work'): ?>
                            <i class="ri-building-line"></i>
                        <?php else: ?>
                            <i class="ri-map-pin-line"></i>
                        <?php endif; ?>

                        <span><?= $type ?></span>

                    </label>

                <?php endforeach; ?>

            </div>

            <label class="input-label">House / Flat No</label>

            <input type="text" name="house_no" class="form-input" value="<?= htmlspecialchars($address['house_no']) ?>">

            <label class="input-label">
                Apartment / Building
            </label>

            <input type="text" name="apartment" class="form-input"
                value="<?= htmlspecialchars($address['apartment']) ?>">

            <label class="input-label">
                Landmark
            </label>

            <input type="text" name="landmark" class="form-input" value="<?= htmlspecialchars($address['landmark']) ?>">

            <label class="input-label">
                Area / Locality
            </label>

            <input type="text" name="area" class="form-input" value="<?= htmlspecialchars($address['area']) ?>">

            <label class="input-label">
                City
            </label>

            <input type="text" name="city" class="form-input" value="<?= htmlspecialchars($address['city']) ?>">

            <label class="input-label">
                State
            </label>

            <input type="text" name="state" class="form-input" value="<?= htmlspecialchars($address['state']) ?>">

            <label class="input-label">
                Pincode
            </label>

            <input type="text" name="pincode" maxlength="6" class="form-input"
                value="<?= htmlspecialchars($address['pincode']) ?>">

            <div style="
                    margin:20px 0;
                    display:flex;
                    align-items:center;
                    gap:10px;
                ">

                <input type="checkbox" id="default" name="is_default" <?= $address['is_default'] ? 'checked' : ''; ?>>

                <label for="default">
                    Make this my default address
                </label>

            </div>
            <input type="hidden" name="latitude" id="latitude"
                value="<?= htmlspecialchars($address['latitude'] ?? '') ?>">

            <input type="hidden" name="longitude" id="longitude"
                value="<?= htmlspecialchars($address['longitude'] ?? '') ?>">

            <button class="btn-primary" style="width:100%;">

                <?= $isEdit
                    ? 'Update Address'
                    : 'Save Address'; ?>

            </button>

        </form>

    </div>

</div>
<style>
    .input-label {
        display: block;
        margin: 15px 0 6px;
        font-weight: 600;
        color: var(--text-main);
    }

    .form-input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 15px;
        background: #fff;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
    }

    .address-type {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 15px;
    }

    .type-card {

        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        position: relative;
        transition: .25s;
        background: #fff;

    }

    .type-card input {

        display: none;

    }

    .type-card i {

        font-size: 24px;
        color: var(--primary);
        display: block;
        margin-bottom: 8px;

    }

    .type-card span {

        font-weight: 600;
        color: var(--text-main);

    }

    .type-card:has(input:checked) {

        border: 2px solid var(--primary);
        background: var(--primary-light);

    }

    .location-search {

        margin-bottom: 12px;

    }

    #addressMap {

        width: 100%;
        height: 350px;
        margin-top: 15px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #ddd;

    }

    #mapWrapper {
        position: relative;
        width: 100%;
    }

    #addressMap {
        width: 100%;
        height: 350px;
        margin-top: 15px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #ddd;
    }

    .center-pin {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -100%);
        z-index: 9999;
        pointer-events: none;
    }

    .center-pin i {
        font-size: 42px;
        color: #e53935;
    }

    .center-pin i {

        font-size: 42px;

        color: #e53935;

    }

    @media(max-width:600px) {

        .address-type {

            grid-template-columns: 1fr;

        }

    }
</style>
<?php include 'includes/bottom-nav.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    document.getElementById('addressForm').addEventListener('submit', function (e) {

        let fullName = document.querySelector('[name=full_name]').value.trim();
        let phone = document.querySelector('[name=phone]').value.trim();
        let addressType = document.querySelector('input[name=address_type]:checked');
        let houseNo = document.querySelector('[name=house_no]').value.trim();
        let apartment = document.querySelector('[name=apartment]').value.trim();
        let landmark = document.querySelector('[name=landmark]').value.trim();
        let area = document.querySelector('[name=area]').value.trim();
        let city = document.querySelector('[name=city]').value.trim();
        let state = document.querySelector('[name=state]').value.trim();
        let pincode = document.querySelector('[name=pincode]').value.trim();
        let latitude = document.getElementById('latitude').value.trim();
        let longitude = document.getElementById('longitude').value.trim();

        const alphaRegex = /^[A-Za-z ]+$/;
        const phoneRegex = /^[6-9][0-9]{9}$/;
        const pinRegex = /^[0-9]{6}$/;

        if (fullName === '') {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter full name.', 'warning');
        }

        if (fullName.length < 3 || fullName.length > 100 || !alphaRegex.test(fullName)) {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter a valid full name.', 'warning');
        }

        if (!phoneRegex.test(phone)) {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter a valid 10-digit mobile number.', 'warning');
        }

        if (!addressType) {
            e.preventDefault();
            return Swal.fire('Validation', 'Please select address type.', 'warning');
        }

        if (houseNo === '') {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter House / Flat No.', 'warning');
        }

        if (apartment.length > 150) {
            e.preventDefault();
            return Swal.fire('Validation', 'Apartment / Building is too long.', 'warning');
        }

        if (landmark.length > 150) {
            e.preventDefault();
            return Swal.fire('Validation', 'Landmark is too long.', 'warning');
        }

        if (area === '') {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter Area / Locality.', 'warning');
        }

        if (city === '' || !alphaRegex.test(city)) {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter a valid City.', 'warning');
        }

        if (state === '' || !alphaRegex.test(state)) {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter a valid State.', 'warning');
        }

        if (!pinRegex.test(pincode)) {
            e.preventDefault();
            return Swal.fire('Validation', 'Enter a valid 6-digit Pincode.', 'warning');
        }

        if (latitude === '' || longitude === '') {
            e.preventDefault();
            return Swal.fire('Validation', 'Please select your location on the map.', 'warning');
        }

    });
    // =========================
    // Leaflet Map Initialization
    // =========================

    let savedLat = <?= !empty($address['latitude']) ? $address['latitude'] : 'null'; ?>;
    let savedLng = <?= !empty($address['longitude']) ? $address['longitude'] : 'null'; ?>;

    const map = L.map('addressMap', {
        zoomControl: true
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // =========================
    // Initial Map Location
    // =========================

    if (savedLat && savedLng) {

        // Edit Mode
        map.setView([savedLat, savedLng], 17);

        updateAddressFromMap(savedLat, savedLng);

    } else {

        // Add Mode
        if (navigator.geolocation) {

            navigator.geolocation.getCurrentPosition(

                function (position) {

                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    map.setView([lat, lng], 18);

                    updateAddressFromMap(lat, lng);

                },

                function () {

                    // If permission denied
                    map.setView([17.3850, 78.4867], 15);

                },

                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }

            );

        } else {

            map.setView([17.3850, 78.4867], 15);

        }

    }
    // =========================
    // Use Current Location
    // =========================

    document.getElementById('currentLocationBtn').addEventListener('click', function () {

        if (!navigator.geolocation) {

            Swal.fire({
                icon: 'error',
                title: 'Location Not Supported',
                text: 'Your browser does not support location services.'
            });

            return;
        }

        navigator.geolocation.getCurrentPosition(

            function (position) {

                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;

                map.setView([lat, lng], 18);

                updateAddressFromMap(lat, lng);

            },

            function () {

                Swal.fire({
                    icon: 'error',
                    title: 'Location Error',
                    text: 'Unable to fetch your current location.'
                });

            },

            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }

        );

    });
    // =========================
    // Reverse Geocoding
    // =========================

    async function updateAddressFromMap(lat, lng) {

        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        try {

            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`
            );

            const data = await response.json();

            if (!data.address) return;

            const a = data.address;
            console.log(data);
            console.log(a);

            // Fill address fields only if empty
            if (!document.querySelector('[name=house_no]').value.trim()) {
                document.querySelector('[name=house_no]').value =
                    a.house_number || '';
            }

            document.querySelector('[name=landmark]').value =
                a.road ||
                a.neighbourhood ||
                a.suburb ||
                a.residential ||
                a.hamlet ||
                '';

            const area = [
                a.suburb,
                a.neighbourhood,
                a.residential,
                a.hamlet,
                a.village,
                a.city_district,
                a.quarter,
                a.road
            ].filter(Boolean);

            document.querySelector('[name=area]').value = area.join(', ');

            document.querySelector('[name=city]').value =
                a.city ||
                a.town ||
                a.village ||
                a.municipality ||
                a.county ||
                '';

            document.querySelector('[name=state]').value =
                a.state ||
                a.state_district ||
                '';

            document.querySelector('[name=pincode]').value =
                a.postcode ||
                '';

        } catch (e) {

            console.log("Reverse geocoding failed", e);

        }

    }
    // Update address whenever map stops moving

    map.on('moveend', function () {

        const center = map.getCenter();

        updateAddressFromMap(
            center.lat,
            center.lng
        );

    });
    // =========================
    // Search Location
    // =========================

    document.getElementById('locationSearch').addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') {
            return;
        }

        e.preventDefault();

        const query = this.value.trim();

        if (query === '') {
            return;
        }

        try {

            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}`
            );

            const results = await response.json();

            if (!results.length) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Location Not Found',
                    text: 'Please search another location.'
                });

                return;

            }

            const lat = parseFloat(results[0].lat);
            const lng = parseFloat(results[0].lon);

            map.setView([lat, lng], 17);

        } catch (error) {

            Swal.fire({
                icon: 'error',
                title: 'Search Failed',
                text: 'Unable to search location.'
            });

        }

    });

</script>