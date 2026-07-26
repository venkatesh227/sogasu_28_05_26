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
$errors = [];
$formValues = [
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
    'is_default' => 0,
    'latitude' => '',
    'longitude' => ''
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

$formValues = [
    'full_name' => $address['full_name'] ?? '',
    'phone' => $address['phone'] ?? '',
    'address_type' => $address['address_type'] ?? 'Home',
    'house_no' => $address['house_no'] ?? '',
    'apartment' => $address['apartment'] ?? '',
    'landmark' => $address['landmark'] ?? '',
    'area' => $address['area'] ?? '',
    'city' => $address['city'] ?? '',
    'state' => $address['state'] ?? '',
    'pincode' => $address['pincode'] ?? '',
    'is_default' => (int) ($address['is_default'] ?? 0),
    'latitude' => $address['latitude'] ?? '',
    'longitude' => $address['longitude'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address_type = trim((string) ($_POST['address_type'] ?? ''));
    $house_no = trim((string) ($_POST['house_no'] ?? ''));
    $apartment = trim((string) ($_POST['apartment'] ?? ''));
    $landmark = trim((string) ($_POST['landmark'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $state = trim((string) ($_POST['state'] ?? ''));
    $pincode = trim((string) ($_POST['pincode'] ?? ''));
    $latitude = trim((string) ($_POST['latitude'] ?? ''));
    $longitude = trim((string) ($_POST['longitude'] ?? ''));

    $formValues = [
        'full_name' => $full_name,
        'phone' => $phone,
        'address_type' => $address_type !== '' ? $address_type : ($address['address_type'] ?? 'Home'),
        'house_no' => $house_no,
        'apartment' => $apartment,
        'landmark' => $landmark,
        'area' => $area,
        'city' => $city,
        'state' => $state,
        'pincode' => $pincode,
        'is_default' => $is_default,
        'latitude' => $latitude,
        'longitude' => $longitude
    ];

    if ($full_name == '') {
        $errors['full_name'] = "Full Name is required";
    } elseif (strlen($full_name) < 3 || strlen($full_name) > 100 || !preg_match('/^[A-Za-z ]+$/', $full_name)) {
        $errors['full_name'] = "Enter a valid Full Name";
    }

    if ($phone == '') {
        $errors['phone'] = "Mobile Number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = "Mobile Number must contain exactly 10 digits";
    } elseif (!preg_match('/^[6-9]/', $phone)) {
        $errors['phone'] = "Mobile Number must start with 6, 7, 8 or 9";
    }

    if (empty($address_type)) {
        $errors['address_type'] = "Select Address Type";
    }

    if ($house_no == '') {
        $errors['house_no'] = "House / Flat No is required";
    }

    if (strlen($apartment) > 150) {
        $errors['apartment'] = "Apartment / Building is too long";
    }

    if (strlen($landmark) > 150) {
        $errors['landmark'] = "Landmark is too long";
    }

    if ($area == '') {
        $errors['area'] = "Area / Locality is required";
    }

    if ($city == '') {
        $errors['city'] = "City is required";
    } elseif (!preg_match('/^[A-Za-z ]+$/', $city)) {
        $errors['city'] = "Enter a valid City";
    }

    if ($state == '') {
        $errors['state'] = "State is required";
    } elseif (!preg_match('/^[A-Za-z ]+$/', $state)) {
        $errors['state'] = "Enter a valid State";
    }

    if (!preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors['pincode'] = "Enter a valid 6-digit Pincode";
    }

    if ($latitude == '' || $longitude == '') {
        $errors['location'] = "Please select your location on the map";
    }

    if (empty($errors)) {

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
            <?php if (!empty($errors['location'])): ?>
                <span class="text-red">
                    <?= htmlspecialchars($errors['location']) ?>
                </span>
            <?php endif; ?>

            <label class="input-label">Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" class="form-input"
                value="<?= htmlspecialchars($formValues['full_name']) ?>">
            <span class="text-red">
                <?= $errors['full_name'] ?? '' ?>
            </span>

            <label class="input-label">Mobile Number <span class="required">*</span></label>
            <input type="tel" name="phone" class="form-input" maxlength="10"
                value="<?= htmlspecialchars($formValues['phone']) ?>">
            <span class="text-red">
                <?= $errors['phone'] ?? '' ?>
            </span>

            <label class="input-label">Address Type</label>

            <div class="address-type">

                <?php

                $types = ['Home', 'Work', 'Other'];

                foreach ($types as $type):

                    ?>

                    <label class="type-card">

                        <input type="radio" name="address_type" value="<?= $type ?>" <?= ($formValues['address_type'] == $type) ? 'checked' : ''; ?>>

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
                <span class="text-red">
                    <?= $errors['address_type'] ?? '' ?>
                </span>

            </div>

            <label class="input-label">House / Flat No <span class="required">*</span></label>

            <input type="text" name="house_no" class="form-input"
                value="<?= htmlspecialchars($formValues['house_no']) ?>">
            <span class="text-red">
                <?= $errors['house_no'] ?? '' ?>
            </span>

            <label class="input-label">
                Apartment / Building
            </label>

            <input type="text" name="apartment" class="form-input"
                value="<?= htmlspecialchars($formValues['apartment']) ?>">
            <span class="text-red">
                <?= $errors['apartment'] ?? '' ?>
            </span>

            <label class="input-label">
                Landmark
            </label>

            <input type="text" name="landmark" class="form-input"
                value="<?= htmlspecialchars($formValues['landmark']) ?>">
            <span class="text-red">
                <?= $errors['landmark'] ?? '' ?>
            </span>

            <label class="input-label">
                Area / Locality <span class="required">*</span>
            </label>

            <input type="text" name="area" class="form-input" value="<?= htmlspecialchars($formValues['area']) ?>">
            <span class="text-red">
                <?= $errors['area'] ?? '' ?>
            </span>

            <label class="input-label">

                City <span class="required">*</span>
            </label>

            <input type="text" name="city" class="form-input" value="<?= htmlspecialchars($formValues['city']) ?>">
            <span class="text-red">
                <?= $errors['city'] ?? '' ?>
            </span>

            <label class="input-label">
                State <span class="required">*</span>
            </label>

            <input type="text" name="state" class="form-input" value="<?= htmlspecialchars($formValues['state']) ?>">
            <span class="text-red">
                <?= $errors['state'] ?? '' ?>
            </span>

            <label class="input-label">
                Pincode <span class="required">*</span>
            </label>

            <input type="text" name="pincode" maxlength="6" class="form-input"
                value="<?= htmlspecialchars($formValues['pincode']) ?>">
            <span class="text-red">
                <?= $errors['pincode'] ?? '' ?>
            </span>

            <div style="
                    margin:20px 0;
                    display:flex;
                    align-items:center;
                    gap:10px;
                ">

                <input type="checkbox" id="default" name="is_default" <?= !empty($formValues['is_default']) ? 'checked' : ''; ?>>

                <label for="default">
                    Make this my default address
                </label>

            </div>
            <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($formValues['latitude']) ?>">

            <input type="hidden" name="longitude" id="longitude"
                value="<?= htmlspecialchars($formValues['longitude']) ?>">

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

    #mapWrapper {
        position: relative;
        width: 100%;
        overflow: hidden;
        border-radius: 12px;
        z-index: 1;
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
        z-index: 500;
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

    .required {
        color: #dc2626;
    }

    .text-red {
        color: #dc2626;
        font-size: 13px;
        margin-top: 4px;
        display: block;
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
    // =========================
    // Leaflet Map Initialization
    // =========================

    let savedLat = <?= !empty($address['latitude']) ? $address['latitude'] : 'null'; ?>;
    let savedLng = <?= !empty($address['longitude']) ? $address['longitude'] : 'null'; ?>;

    const map = L.map('addressMap', {
        zoomControl: true
    });
    window.addEventListener('load', function () {
        setTimeout(function () {
            map.invalidateSize();
        }, 200);
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