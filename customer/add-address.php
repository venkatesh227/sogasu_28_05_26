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
                is_default,
                created_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?,?
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

            <label class="input-label">Full Name</label>
            <input type="text" name="full_name" class="form-input" required
                value="<?= htmlspecialchars($address['full_name']) ?>">

            <label class="input-label">Mobile Number</label>
            <input type="tel" name="phone" class="form-input" maxlength="10" required
                value="<?= htmlspecialchars($address['phone']) ?>">

            <label class="input-label">Address Type</label>

            <div class="address-type">

                <?php

                $types = ['Home', 'Work', 'Other'];

                foreach ($types as $type):

                    ?>

                    <label class="type-card">

                        <input type="radio" name="address_type" value="<?= $type ?>"
                            <?= $address['address_type'] == $type ? 'checked' : ''; ?>>

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

            <input type="text" name="house_no" class="form-input" required
                value="<?= htmlspecialchars($address['house_no']) ?>">

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

            <input type="text" name="area" class="form-input" required
                value="<?= htmlspecialchars($address['area']) ?>">

            <label class="input-label">
                City
            </label>

            <input type="text" name="city" class="form-input" required
                value="<?= htmlspecialchars($address['city']) ?>">

            <label class="input-label">
                State
            </label>

            <input type="text" name="state" class="form-input" required
                value="<?= htmlspecialchars($address['state']) ?>">

            <label class="input-label">
                Pincode
            </label>

            <input type="text" name="pincode" maxlength="6" class="form-input" required
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

    @media(max-width:600px) {

        .address-type {

            grid-template-columns: 1fr;

        }

    }
</style>

<?php include 'includes/bottom-nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    document.getElementById('addressForm')
        .addEventListener('submit', function (e) {

            let phone = document
                .querySelector('[name=phone]')
                .value
                .trim();

            let pincode = document
                .querySelector('[name=pincode]')
                .value
                .trim();

            if (!/^[0-9]{10}$/.test(phone)) {

                e.preventDefault();

                Swal.fire({

                    icon: 'error',
                    title: 'Invalid Mobile Number',
                    text: 'Enter valid 10 digit mobile number.'

                });

                return;

            }

            if (!/^[0-9]{6}$/.test(pincode)) {

                e.preventDefault();

                Swal.fire({

                    icon: 'error',
                    title: 'Invalid Pincode',
                    text: 'Enter valid 6 digit pincode.'

                });

                return;

            }

        });

</script>