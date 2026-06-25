<?php
session_start();
require_once '../includes/razorpay-config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (empty($_POST['customer_id']))
        $errors['customer_id'] = "Customer is required";
    if (empty($_POST['category_id']))
        $errors['category_id'] = "Category is required";
    if (empty($_POST['sub_category_id']))
        $errors['sub_category_id'] = "Sub Category is required";
    if (empty($_POST['due_date']))
        $errors['due_date'] = "Due Date is required";
    if (empty($_POST['mobile_number']))
        $errors['mobile_number'] = "Mobile Number is required";


    if (empty($_POST['delivery_address']))
        $errors['delivery_address'] = "Delivery Address is required";

    if (empty($_POST['design_notes']))
        $errors['design_notes'] = "Design Description / Remarks is required";
    if (empty($_POST['fabric_details']))
        $errors['fabric_details'] = "Fabric Details is required";

    if (empty($_POST['base_price']))
        $errors['base_price'] = "Base Price is required";
    if (
        !empty($_POST['advance_payment_mode']) &&
        $_POST['advance_payment_mode'] !== 'Cash' &&
        empty(trim($_POST['transaction_reference'] ?? ''))
    ) {
        $errors['transaction_reference'] = "Transaction Reference is required";
    }
    if (
        ($_POST['order_type'] ?? 'inhouse') === 'outsource' &&
        (!isset($_POST['outsource_credit']) || $_POST['outsource_credit'] === '')
    ) {
        $errors['outsource_credit'] = "Outsource Credit is required";
    }

    if (
        !isset($_POST['measurements']) ||
        !is_array($_POST['measurements']) ||
        count(array_filter($_POST['measurements'])) == 0
    ) {
        $errors['measurements'] = "Measurements are required";
    }
    if (empty($errors)) {
        file_put_contents('debug_post.txt', print_r($_POST, true));
        try {
            $customer_id = $_POST['customer_id'];
            $category_id = $_POST['category_id'] ?: null;
            $order_type = $_POST['order_type'] ?? 'inhouse';
            $sub_category_id = $_POST['sub_category_id'] ?: null;
            $fabric_details = $_POST['fabric_details'] ?? '';
            $due_date = $_POST['due_date'] ?: null;
            $design_notes = $_POST['design_notes'] ?? '';
            $supervisor_id = null;
            $order_status = 'pending';
            $base_price = $_POST['base_price'] ?: 0;
            $extra_charges = $_POST['extra_charges'] ?: 0;
            $advance_amount = $_POST['advance_amount'] ?: 0;
            $outsource_credit = 0;

            if (($order_type ?? 'inhouse') === 'outsource') {
                $outsource_credit = $_POST['outsource_credit'] ?? 0;
            }
            $advance_payment_mode = !empty($_POST['advance_payment_mode']) ? $_POST['advance_payment_mode'] : null;
            $transaction_reference = !empty($_POST['transaction_reference'])
                ? trim($_POST['transaction_reference'])
                : null;

            // Calculate Total including Additional Services
            $services_total = 0;
            $selected_services = array_unique($_POST['selected_services'] ?? []);
            if (!empty($selected_services)) {
                $placeholders = implode(',', array_fill(0, count($selected_services), '?'));
                $stmt_sp = $pdo->prepare("SELECT SUM(base_price) FROM services WHERE id IN ($placeholders)");
                $stmt_sp->execute($selected_services);
                $services_total = $stmt_sp->fetchColumn() ?: 0;
            }

            $total_amount = $base_price + $extra_charges + $services_total;
            if ($advance_amount > $total_amount) {
                throw new Exception("Advance amount cannot exceed total amount");
            }
            $family_member_id = !empty($_POST['family_member_id']) ? $_POST['family_member_id'] : null;
            $measurement_unit = $_POST['measurement_unit'] ?? 'CMS';
            $current_user_id = $_SESSION['user_id'] ?? null;
            $material_image = null;
            $referral_image = null;

            $uploadDir = '../uploads/orders/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (isset($_FILES['fabric_photos']) && !empty($_FILES['fabric_photos']['name'][0])) {

                $ext = pathinfo($_FILES['fabric_photos']['name'][0], PATHINFO_EXTENSION);

                $materialFileName = "material_" . time() . "." . $ext;

                move_uploaded_file(
                    $_FILES['fabric_photos']['tmp_name'][0],
                    $uploadDir . $materialFileName
                );

                $material_image = "uploads/orders/" . $materialFileName;
            }

            if (isset($_FILES['sample_photos']) && !empty($_FILES['sample_photos']['name'][0])) {

                $ext = pathinfo($_FILES['sample_photos']['name'][0], PATHINFO_EXTENSION);

                $referralFileName = "referral_" . time() . "." . $ext;

                move_uploaded_file(
                    $_FILES['sample_photos']['tmp_name'][0],
                    $uploadDir . $referralFileName
                );

                $referral_image = "uploads/orders/" . $referralFileName;
            }

            // Generate Order Code
            $order_code = "ORD-" . date('Y') . "-" . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $table_name = ($order_type === 'outsource')
                ? 'outsource_orders'
                : 'orders';
            if ($order_type === 'inhouse') {

                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        order_code,
                        customer_id,
                        family_member_id,
                        category_id,
                        sub_category_id,
                        fabric_details,
                        notes,
                        material_image,
                        referral_image,
                        order_status,
                        status_history,
                        payment_status,
                        supervisor_id,
                        base_price,
                        extra_charges,
                        total_amount,
                        advance_amount,
                        paid_amount,
                        advance_payment_mode,
                        transaction_reference,
                        due_date,
                        measurement_unit
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $order_code,
                    $customer_id,
                    $family_member_id,
                    $category_id,
                    $sub_category_id,
                    $fabric_details,
                    $design_notes,
                    $material_image,
                    $referral_image,
                    $order_status,
                    'pending',
                    'pending',
                    $supervisor_id,
                    $base_price,
                    $extra_charges,
                    $total_amount,
                    $advance_amount,
                    0,
                    $advance_payment_mode,
                    $transaction_reference,
                    $due_date,
                    $measurement_unit
                ]);
            } else {

                $stmt = $pdo->prepare("
            INSERT INTO outsource_orders (
                order_code,
                order_type,
                customer_id,
                family_member_id,
                category_id,
                sub_category_id,
                fabric_details,
                notes,
                material_image,
                referral_image,
                order_status,
                payment_status,
                supervisor_id,
                base_price,
                extra_charges,
                total_amount,
                outsource_credit,
                advance_amount,
                paid_amount,
                advance_payment_mode,
                transaction_reference,
                due_date,
                measurement_unit
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

                $stmt->execute([
                    $order_code,
                    'outsource',
                    $customer_id,
                    $family_member_id,
                    $category_id,
                    $sub_category_id,
                    $fabric_details,
                    $design_notes,
                    $material_image,
                    $referral_image,
                    $order_status,
                    'pending',
                    $supervisor_id,
                    $base_price,
                    $extra_charges,
                    $total_amount,
                    $outsource_credit,
                    $advance_amount,
                    0,
                    $advance_payment_mode,
                    $transaction_reference,
                    $due_date,
                    $measurement_unit
                ]);
            }

            $order_id = $pdo->lastInsertId();
            if ($order_type === 'outsource') {

                $empStmt = $pdo->prepare("
                    SELECT id
                    FROM employees
                    WHERE employee_type = 'outsource'
                    AND is_deleted = 0
                ");
                $empStmt->execute();
                $outsourceEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

                $notificationTitle = 'New Outsource Order Available';
                $notificationMessage = 'Order ' . $order_code . ' is available';

                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications
                    (employee_id, title, message)
                    VALUES (?, ?, ?)
                ");

                foreach ($outsourceEmployees as $emp) {
                    $notifStmt->execute([
                        $emp['id'],
                        $notificationTitle,
                        $notificationMessage
                    ]);
                }
            }
            //         try {
            //             $payable_amount = $advance_amount > 0 ? $advance_amount : $total_amount;
            //             $payment = $api->paymentLink->create([

            //                 'amount' => $payable_amount * 100,

            //                 'currency' => 'INR',

            //                 'accept_partial' => false,

            //                 'description' => "Payment for Order #{$order_code}",

            //                 'customer' => [
            //                     'name' => 'Customer',
            //                     'contact' => preg_replace('/[^0-9]/', '', $_POST['mobile_number'])
            //                 ],

            //                 'notify' => [
            //                     'sms' => false,
            //                     'email' => false
            //                 ],

            //                 'reminder_enable' => false,
            //                 'callback_url' => 'http://localhost/sogasu_28_05_26/admin/payment-verify.php',

            //                 'callback_method' => 'get'

            //             ]);

            //             $payment_link_id = $payment['id'];

            //             $payment_link = $payment['short_url'];

            //             $stmtPayment = $pdo->prepare("
            //     UPDATE orders
            //     SET
            //         razorpay_payment_link_id = ?,
            //         payment_link = ?
            //     WHERE id = ?
            // ");

            //             $stmtPayment->execute([
            //                 $payment_link_id,
            //                 $payment_link,
            //                 $order_id
            //             ]);

            //         } catch (Exception $e) {

            //             file_put_contents(
            //                 'razorpay_error_log.txt',
            //                 date('Y-m-d H:i:s') . " - " . $e->getMessage() . PHP_EOL,
            //                 FILE_APPEND
            //             );
            //         }


            // Save Additional Services
            if (!empty($selected_services)) {

                // remove duplicate service ids
                $selected_services = array_unique($selected_services);

                $stmt_s = $pdo->prepare("
                    INSERT INTO order_services 
                    (order_id, order_type, service_id, service_price) 
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($selected_services as $service_id) {

                    $stmt_sp = $pdo->prepare("
            SELECT base_price 
            FROM services 
            WHERE id = ?
        ");

                    $stmt_sp->execute([$service_id]);

                    $s_price = $stmt_sp->fetchColumn() ?: 0;

                    $stmt_s->execute([
                        $order_id,
                        $order_type,
                        $service_id,
                        $s_price
                    ]);
                }
            }

            // Save Measurements
            if (isset($_POST['measurements']) && is_array($_POST['measurements'])) {
                file_put_contents('debug_measurements.txt', "Found measurements array for order $order_id\n", FILE_APPEND);
                $stmt_m = $pdo->prepare("
                    INSERT INTO order_measurements 
                    (order_id, order_type, key_name, measurement_value) 
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($_POST['measurements'] as $key => $value) {
                    file_put_contents('debug_measurements.txt', "Attempting to save: $key = $value\n", FILE_APPEND);
                    if ($value !== '') {
                        $stmt_m->execute([
                            $order_id,
                            $order_type,
                            $key,
                            $value
                        ]);
                    }
                }
            }

            // Handle File Uploads
            $uploadDir = '../uploads/orders/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadFile = function ($files, $type, $order_id, $pdo) use ($uploadDir) {
                if (!empty($files['name'][0])) {
                    foreach ($files['name'] as $i => $name) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $filename = $type . "_" . $order_id . "_" . time() . "_" . $i . "." . $ext;
                            $target = $uploadDir . $filename;

                            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                                $stmt_img = $pdo->prepare("INSERT INTO order_images (order_id, image_path, image_type) VALUES (?, ?, ?)");
                                $stmt_img->execute([$order_id, "uploads/orders/" . $filename, $type]);
                            }
                        }
                    }
                }
            };

            if (isset($_FILES['fabric_photos'])) {
                $uploadFile($_FILES['fabric_photos'], 'fabric', $order_id, $pdo);
            }
            if (isset($_FILES['sample_photos'])) {
                $uploadFile($_FILES['sample_photos'], 'sample', $order_id, $pdo);
            }

            if (!isset($_POST['measurements']) || !is_array($_POST['measurements'])) {
                file_put_contents('debug_measurements.txt', "NO measurements array found in POST for order $order_id\n", FILE_APPEND);
            }

            $_SESSION['success'] = "Order #$order_code placed successfully!";

            if ($order_type === 'outsource') {
                header("Location: outsourcing_orders.php");
            } else {
                header("Location: orders.php");
            }
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = "General Error: " . $e->getMessage();
        }
    }
}

// Fetch Customers
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, phone, address, city, branch FROM customers WHERE is_deleted=0");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active branches and their measurement modes
$branchesStmt = $pdo->query("SELECT branch_name, measurement_mode FROM branches WHERE is_deleted=0");
$branchesList = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve active employee's branch measurement mode
$default_unit = 'CMS';
if (isset($_SESSION['user_id'])) {
    $empStmt = $pdo->prepare("SELECT branch FROM employees WHERE user_id = ? AND is_deleted = 0");
    $empStmt->execute([$_SESSION['user_id']]);
    $user_branch = $empStmt->fetchColumn();
    if ($user_branch) {
        $bStmt = $pdo->prepare("SELECT measurement_mode FROM branches WHERE branch_name = ? AND is_deleted = 0");
        $bStmt->execute([$user_branch]);
        $mode = $bStmt->fetchColumn();
        if ($mode) {
            $default_unit = ($mode === 'Inches') ? 'INCH' : 'CMS';
        }
    }
}

// Fetch Supervisors
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE is_deleted=0 AND job_role = 'Supervisor'");
$supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Employees (all for now, but usually filtered)
$stmt = $pdo->query("SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, j.role_name FROM employees e LEFT JOIN job_roles j ON e.job_role = j.id WHERE e.is_deleted=0 AND (j.role_name != 'Supervisor' OR j.role_name IS NULL)");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Quick Notes
$stmt = $pdo->query("SELECT * FROM quick_notes WHERE is_deleted=0 AND status=1");
$quick_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Family Members
$stmt = $pdo->query("SELECT id, customer_id, member_name, relationship FROM customer_family_members WHERE is_deleted=0");
$family_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories
$stmt = $pdo->query("SELECT id, category_name FROM categories WHERE is_deleted=0");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Services (Sub categories)
$stmt = $pdo->query("SELECT id, category_id, name as service_name, price as base_price, preparation_days FROM sub_categories WHERE is_deleted=0");
$services_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$services = [];
foreach ($services_raw as $s) {
    $services[$s['category_id']][] = $s;
}

// Fetch Measurements Mapping
$stmt = $pdo->query("SELECT mm.sub_category_id, mk.key_name FROM measurement_mapping mm JOIN measurement_keys mk ON mm.key_id = mk.id WHERE mk.is_deleted=0");
$mapping_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$measurement_mapping = [];
foreach ($mapping_raw as $m) {
    $measurement_mapping[$m['sub_category_id']][] = $m['key_name'];
}


// Fetch Standalone Services
$stmt = $pdo->query("SELECT id, service_name, description, base_price, category_id FROM services WHERE is_deleted=0 AND status='active'");
$standalone_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "New Order - Sogasu";
$activePage = "add-order";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Create New Order</h2>
                <p class="text-muted">Enter order & customer details</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Cancel
            </button>
        </div>
    </div>

    <form method="POST" novalidate enctype="multipart/form-data"
        style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">

        <!-- Left Column: Customer & Garment Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <!-- Customer Details -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
">

                    <span style="display:flex; align-items:center; gap:0.5rem;">
                        <i class="ri-user-line" style="color: var(--primary);"></i>
                        Customer Details
                    </span>

                    <span style="display:flex; align-items:center; gap:15px; font-size:14px; font-weight:500;">

                        <span>Type :</span>

                        <label style="display:flex; align-items:center; gap:5px; margin:0;">
                            <input type="radio" name="order_type" value="inhouse" <?= (($_POST['order_type'] ?? 'inhouse') === 'inhouse') ? 'checked' : '' ?>>
                            Inhouse
                        </label>

                        <label style="display:flex; align-items:center; gap:5px; margin:0;">
                            <input type="radio" name="order_type" value="outsource" <?= (($_POST['order_type'] ?? '') === 'outsource') ? 'checked' : '' ?>>
                            Outsource
                        </label>

                    </span>

                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Customer Name <span style="color:red">*</span></label>
                        <div style="position: relative;">
                            <input type="text" class="form-control" placeholder="Search name..." id="customer-search"
                                autocomplete="off" oninput="showSuggestions('name')">
                            <i class="ri-search-line"
                                style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <div id="customer-suggestions" class="suggestions-dropdown" style="display: none;"></div>
                        </div>
                        <?php if (isset($errors['customer_id']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['customer_id']}</div>"; ?>
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label class="form-label">Mobile Number <span style="color:red">*</span></label>
                        <div style="position: relative;">
                            <input type="hidden" name="customer_id" id="customer-id-input">
                            <input type="tel" name="mobile_number" class="form-control" id="customer-mobile"
                                placeholder="Search phone..." autocomplete="off" oninput="showSuggestions('phone')">
                            <?php if (isset($errors['mobile_number']))
                                echo "<div style='color:red;font-size:0.85rem;margin-top:5px'>{$errors['mobile_number']}</div>"; ?>
                            <i class="ri-search-line"
                                style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <div id="phone-suggestions" class="suggestions-dropdown" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Order For</label>
                        <select name="family_member_id" id="family-member-select" class="form-select">
                            <option value="">Self (Primary Customer)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Address <span style="color:red">*</span></label>
                        <input type="text" name="delivery_address" class="form-control" id="customer-address"
                            placeholder="Address...">
                        <?php if (isset($errors['delivery_address']))
                            echo "<div style='color:red;font-size:0.85rem;margin-top:5px'>{$errors['delivery_address']}</div>"; ?>
                    </div>
                </div>
            </div>

            <!-- Garment Selection -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-t-shirt-line" style="color: var(--primary);"></i> Garment Information
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Category <span style="color:red">*</span></label>
                        <select class="form-select" id="order-cat-select" name="category_id"
                            onchange="updateOrderSubCats()">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['category_id']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sub Category <span style="color:red">*</span></label>
                        <select class="form-select" id="order-sub-cat-select" name="sub_category_id"
                            onchange="loadMeasurements()">
                            <option value="">Select Sub Category</option>
                        </select>
                        <?php if (isset($errors['sub_category_id']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['sub_category_id']}</div>"; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Fabric Details <span style="color:red">*</span></label>
                        <input type="text" name="fabric_details" class="form-control"
                            placeholder="Source & Color (e.g. Saree Blouse, Red)">
                        <?php if (isset($errors['fabric_details']))
                            echo "<div style='color:red;font-size:0.85rem;margin-top:5px'>{$errors['fabric_details']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date <span style="color:red">*</span></label>
                        <input type="date" name="due_date" class="form-control" id="due-date">
                        <?php if (isset($errors['due_date']))
                            echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['due_date']}</div>"; ?>
                    </div>
                </div>
            </div>

            <!-- Dynamic Measurements Section -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-ruler-line" style="color: var(--primary);"></i> Measurements <span
                            style="color:red">*</span>
                    </span>
                    <div
                        style="display: flex; background: #f1f5f9; padding: 2px; border-radius: 6px; font-size: 0.75rem; color: #64748b;">
                        <label
                            style="margin: 0; padding: 4px 10px; cursor: pointer; border-radius: 4px; font-weight: 600; transition: all 0.2s;"
                            id="unit-cms-label" class="<?= $default_unit === 'CMS' ? 'unit-active' : '' ?>">
                            <input type="radio" name="measurement_unit" value="CMS" <?= $default_unit === 'CMS' ? 'checked' : '' ?> style="display: none;" onchange="updateUnitStyle('CMS')"> CMS
                        </label>
                        <label
                            style="margin: 0; padding: 4px 10px; cursor: pointer; border-radius: 4px; font-weight: 600; transition: all 0.2s;"
                            id="unit-inch-label" class="<?= $default_unit === 'INCH' ? 'unit-active' : '' ?>">
                            <input type="radio" name="measurement_unit" value="INCH" <?= $default_unit === 'INCH' ? 'checked' : '' ?> style="display: none;" onchange="updateUnitStyle('INCH')"> INCHES
                        </label>
                    </div>
                </h3>

                <!-- Empty State -->
                <div id="measurements-empty"
                    style="text-align: center; padding: 2rem; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 6px;">
                    <i class="ri-layout-grid-line" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    Select a Category & Sub Category above to load measurement fields.
                </div>

                <!-- Fields Container -->
                <div id="measurements-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                </div>
                <?php if (isset($errors['measurements']))
                    echo "<div style='color:red;font-size:0.85rem;margin-top:10px'>{$errors['measurements']}</div>"; ?>
            </div>

            <!-- Photos & References -->
            <div
                style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-camera-line" style="color: var(--primary);"></i> Photos & References
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <!-- Fabric Upload -->
                    <div>
                        <label class="form-label" style="font-weight: 700;">Fabric Photos</label>
                        <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.75rem;">Upload images of the
                            fabric to be stitched.</p>
                        <div class="upload-area" onclick="document.getElementById('fabric_photos').click()"
                            id="fabric-preview" style="position: relative; overflow: hidden;">

                            <i class="ri-upload-cloud-line"></i>

                            <span>Click to upload fabrics</span>

                            <input type="file" name="fabric_photos[]" id="fabric_photos" multiple accept="image/*"
                                style="display: none;" onchange="previewImages(this, 'fabric-preview')">
                        </div>
                    </div>

                    <!-- Sample Upload -->
                    <div>
                        <label class="form-label" style="font-weight: 700;">Sample References</label>
                        <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.75rem;">Upload sample designs or
                            reference photos.</p>
                        <div class="upload-area" onclick="document.getElementById('sample_photos').click()"
                            id="sample-preview" style="border-color: #e2e8f0; position: relative; overflow: hidden;">

                            <i class="ri-image-add-line" style="color: #64748b;"></i>

                            <span>Click to upload samples</span>

                            <input type="file" name="sample_photos[]" id="sample_photos" multiple accept="image/*"
                                style="display: none;" onchange="previewImages(this, 'sample-preview')">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work & Notes -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-clipboard-line" style="color: var(--primary);"></i> Notes & Design <span
                        style="color:red">*</span>
                </h3>
                <div class="form-group">
                    <label class="form-label">Design Description / Remarks</label>
                    <textarea class="form-control" name="design_notes" id="design-notes" rows="3"
                        placeholder="Neck depth, embroidery details, piping etc."></textarea>
                    <?php if (isset($errors['design_notes']))
                        echo "<div style='color:red;font-size:0.85rem;margin-top:5px'>{$errors['design_notes']}</div>"; ?>
                </div>
                <!-- Predefined Notes -->
                <div style="margin-top: 1rem;">
                    <label class="form-label" style="font-size: 0.75rem; color: #94a3b8;">Quick Add Notes:</label>
                    <div class="notes-chips">
                        <?php foreach ($quick_notes as $qn): ?>
                            <span onclick="toggleNote(this, '<?= htmlspecialchars($qn['note_text']) ?>')"
                                style="background: <?= $qn['color_bg'] ?>; border-color: <?= $qn['color_border'] ?>; color: <?= $qn['color_text'] ?>;">
                                <?= htmlspecialchars($qn['note_text']) ?> +
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Additional Services -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3
                    style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-customer-service-2-line" style="color: var(--primary);"></i> Services & Add-ons
                </h3>
                <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 1rem;">Select additional services to include
                    in this order.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($standalone_services as $srv): ?>
                        <label class="service-checkbox-card"
                            style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                            title="<?= htmlspecialchars($srv['description'] ?? '') ?>">
                            <input type="checkbox" name="selected_services[]" value="<?= $srv['id'] ?>"
                                data-price="<?= $srv['base_price'] ?>" onchange="calculateTotal()"
                                style="width: 18px; height: 18px; cursor: pointer;">
                            <div style="flex: 1;">
                                <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">
                                    <?= htmlspecialchars($srv['service_name']) ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b;">₹
                                    <?= number_format($srv['base_price'], 2) ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Pricing & Summary -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Order Summary</h3>

                <div
                    style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: #64748b;">
                    <span>Order Date</span>
                    <span style="font-weight: 500; color: #1e293b;"><?php echo date('d M Y'); ?></span>
                </div>
                <div
                    style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 0.9rem; color: #64748b;">
                    <span>Order ID</span>
                    <span style="font-weight: 500; color: #1e293b;">#ORD-2026-001</span>
                </div>




                <div style="border-top: 1px solid #f1f5f9; margin: 1rem 0;"></div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Base Price <span style="color:red">*</span></label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" step="0.01" name="base_price" class="form-control" id="base_price"
                            style="padding-left: 2rem;" placeholder="0.00" oninput="calculateTotal()">
                        <?php if (isset($errors['base_price']))
                            echo "<div style='color:red;font-size:0.85rem;margin-top:5px'>{$errors['base_price']}</div>"; ?>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Extra Charges (Work/Lining)</label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" step="0.01" name="extra_charges" class="form-control" id="extra_charges"
                            style="padding-left: 2rem;" placeholder="0.00" oninput="calculateTotal()">
                    </div>
                </div>
                <div class="form-group" id="outsource-credit-group" style="display:none; margin-bottom: 1rem;">
                    <label class="form-label">Outsource Credit <span style="color:red">*</span></label>

                    <div style="position: relative;">
                        <span style="
                            position:absolute;
                            left:1rem;
                            top:50%;
                            transform:translateY(-50%);
                            color:#64748b;
                        ">₹</span>

                        <input type="number" step="0.01" name="outsource_credit" id="outsource_credit"
                            class="form-control" style="padding-left:2rem;" placeholder="0.00"
                            value="<?= htmlspecialchars($_POST['outsource_credit'] ?? '') ?>">
                    </div>

                    <?php if (isset($errors['outsource_credit'])): ?>
                        <div style="color:red;font-size:0.85rem;margin-top:5px">
                            <?= $errors['outsource_credit'] ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Total Amount</label>
                    <div id="total_amount" style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">₹ 0.00</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Advance Amount</label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" name="advance_amount" class="form-control" style="padding-left: 2rem;"
                            placeholder="0.00">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Payment Mode (Advance)</label>
                    <select name="advance_payment_mode" class="form-select">
                        <option value="">-- Select Payment Mode --</option>

                        <option value="Cash" <?= (($_POST['advance_payment_mode'] ?? '') === 'Cash') ? 'selected' : '' ?>>
                            Cash
                        </option>

                        <option value="UPI" <?= (($_POST['advance_payment_mode'] ?? '') === 'UPI') ? 'selected' : '' ?>>
                            UPI
                        </option>

                        <option value="Card" <?= (($_POST['advance_payment_mode'] ?? '') === 'Card') ? 'selected' : '' ?>>
                            Card
                        </option>

                        <option value="Bank Transfer" <?= (($_POST['advance_payment_mode'] ?? '') === 'Bank Transfer') ? 'selected' : '' ?>>
                            Bank Transfer
                        </option>
                    </select>
                </div>
                <div class="form-group" id="transactionReferenceGroup" style="display: <?= (
                    !empty($_POST['advance_payment_mode']) &&
                    $_POST['advance_payment_mode'] !== 'Cash'
                ) ? 'block' : 'none'; ?>; margin-top: 1rem;">

                    <label class="form-label">Transaction Reference <span style="color:red">*</span></label>

                    <input type="text" name="transaction_reference" id="transaction_reference" class="form-control"
                        placeholder="Enter UTR / Transaction ID / Reference Number"
                        value="<?= htmlspecialchars($_POST['transaction_reference'] ?? '') ?>">
                    <?php if (!empty($errors['transaction_reference'])): ?>
                        <small class="text-danger d-block" style="color:red !important;">
                            <?= $errors['transaction_reference'] ?>
                        </small>
                    <?php endif; ?>

                </div>

            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                <button type="submit" name="submit_order" value="1" class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;">Create Order</button>
                <button type="button" class="btn w-full"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Save
                    as Draft</button>
            </div>

        </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
    }

    .suggestion-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background 0.1s;
        border-bottom: 1px solid #f1f5f9;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background: #f8fafc;
    }

    .notes-chips {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .notes-chips span {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        cursor: pointer;
        border: 1px solid;
    }

    .notes-chips span.active {
        background: #4f46e5 !important;
        color: white;
        border-color: #4f46e5;
    }

    .suggestions-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 100;
        margin-top: 4px;
        max-height: 250px;
        overflow-y: auto;
    }

    .unit-active {
        background: white !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        color: #4f46e5 !important;
    }

    /* Modern Upload Styles */
    .upload-area {
        border: 2px dashed #e2e8f0;
        padding: 1.5rem;
        text-align: center;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        background: #f8fafc;
    }

    .upload-area:hover {
        border-color: #4f46e5;
        background: #f1f5f9;
    }

    .upload-area i {
        font-size: 1.75rem;
        color: #4f46e5;
        display: block;
        margin-bottom: 0.5rem;
    }

    .upload-area span {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 500;
    }

    .image-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .service-checkbox-card:hover {
        border-color: #4f46e5 !important;
        background: #f0f4ff !important;
    }

    .service-checkbox-card input:checked+div {
        color: #4f46e5;
    }

    .service-checkbox-card:has(input:checked) {
        border-color: #4f46e5 !important;
        background: #f0f4ff !important;
    }
</style>

<script>
    const customersData = <?= json_encode($customers) ?>;
    const servicesData = <?= json_encode($services) ?>;
    const standaloneServices = <?= json_encode($standalone_services) ?>;
    const measurementMapping = <?= json_encode($measurement_mapping) ?>;
    const familyData = <?= json_encode($family_members) ?>;
    const branchesData = <?= json_encode($branchesList) ?>;
    const branchModes = {};
    branchesData.forEach(b => {
        branchModes[b.branch_name] = b.measurement_mode;
    });

    function showSuggestions(type = 'name') {
        const nameInput = document.getElementById('customer-search').value.toLowerCase();
        const phoneInput = document.getElementById('customer-mobile').value.toLowerCase();
        const suggestions = document.getElementById('customer-suggestions');
        const phoneSuggestions = document.getElementById('phone-suggestions');

        let input = type === 'name' ? nameInput : phoneInput;
        let activeDropdown = type === 'name' ? suggestions : phoneSuggestions;
        let otherDropdown = type === 'name' ? phoneSuggestions : suggestions;

        otherDropdown.style.display = 'none';

        if (input.length === 0) {
            activeDropdown.style.display = 'none';
            return;
        }

        const filtered = customersData.filter(c =>
            type === 'name' ? c.name.toLowerCase().includes(input) : c.phone.includes(input)
        );

        let html = '';
        if (filtered.length > 0) {
            filtered.forEach(c => {
                let fullAddr = c.address || '';
                if (c.city) fullAddr += (fullAddr ? ', ' : '') + c.city;
                const safeAddress = fullAddr.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                html += `
                <div class="suggestion-item" onclick="selectCustomer('${c.id}', '${c.name}', '${c.phone}', '${safeAddress}')">
                    <div style="font-weight: 500; color: #1e293b;">${c.name}</div>
                    <div style="font-size: 0.75rem; color: #64748b;">+91 ${c.phone}</div>
                </div>`;
            });
        } else {
            html += `<div style="padding: 1rem; text-align: center; color: #94a3b8; font-size: 0.85rem;">No customer found</div>`;
        }

        html += `
        <div style="padding: 0.75rem; border-top: 1px solid #f1f5f9;">
            <button type="button" class="btn btn-sm w-full" style="justify-content: center; font-size: 0.8rem; background: #eef2ff; color: #4338ca; border: none;" onclick="window.location.href='add-customer.php'">
                <i class="ri-add-line"></i> Add New Customer
            </button>
        </div>`;

        activeDropdown.innerHTML = html;
        activeDropdown.style.display = 'block';
    }

    // Hide suggestions when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.form-group')) {
            document.getElementById('customer-suggestions').style.display = 'none';
            document.getElementById('phone-suggestions').style.display = 'none';
        }
    });

    function selectCustomer(id, name, phone, address) {
        document.getElementById('customer-id-input').value = id;
        document.getElementById('customer-search').value = name;
        document.getElementById('customer-mobile').value = phone;
        document.getElementById('customer-address').value = address || '';
        document.getElementById('customer-suggestions').style.display = 'none';
        document.getElementById('phone-suggestions').style.display = 'none';

        // Auto-toggle measurement mode based on customer's branch
        const customer = customersData.find(c => c.id == id);
        if (customer && customer.branch && branchModes[customer.branch]) {
            const mode = branchModes[customer.branch];
            const unitValue = mode === 'Inches' ? 'INCH' : 'CMS';
            const radio = document.querySelector(`input[name="measurement_unit"][value="${unitValue}"]`);
            if (radio) {
                radio.checked = true;
                updateUnitStyle(unitValue);
            }
        }

        // Update Family Members
        const familySelect = document.getElementById('family-member-select');
        familySelect.innerHTML = '<option value="">Self (Primary Customer)</option>';

        const members = familyData.filter(m => m.customer_id == id);
        members.forEach(m => {
            let opt = document.createElement('option');
            opt.value = m.id;
            opt.text = m.member_name + ' (' + m.relationship + ')';
            familySelect.add(opt);
        });
    }

    function updateOrderSubCats() {
        const catId = document.getElementById('order-cat-select').value;
        const subCat = document.getElementById('order-sub-cat-select');

        // Clear existing
        subCat.innerHTML = '<option value="">Select Sub Category</option>';

        if (catId && servicesData[catId]) {
            servicesData[catId].forEach(s => {
                let el = document.createElement('option');
                el.text = s.service_name;
                el.value = s.id;
                // store base price in dataset for easy retrieval
                el.dataset.price = s.base_price;
                el.dataset.days = s.preparation_days || 0;
                subCat.add(el);
            });
        }

        // Reset measurements & price
        document.getElementById('measurements-empty').style.display = 'block';
        document.getElementById('measurements-grid').style.display = 'none';
        document.getElementById('base_price').value = '';
        calculateTotal();
    }

    function loadMeasurements() {
        const select = document.getElementById('order-sub-cat-select');
        const subCatId = select.value;
        const grid = document.getElementById('measurements-grid');
        const empty = document.getElementById('measurements-empty');

        if (!subCatId) {
            grid.style.display = 'none';
            empty.style.display = 'block';
            document.getElementById('base_price').value = '';
            calculateTotal();
            return;
        }

        // Set Base Price
        const option = select.options[select.selectedIndex];
        document.getElementById('base_price').value = option.dataset.price || 0;

        // Auto-calculate Due Date
        const prepDays = parseInt(option.dataset.days) || 0;
        if (prepDays > 0) {
            const date = new Date();
            date.setDate(date.getDate() + prepDays);
            const dateStr = date.toISOString().split('T')[0];
            document.getElementById('due-date').value = dateStr;
        }

        calculateTotal();

        empty.style.display = 'none';
        grid.style.display = 'grid';
        grid.innerHTML = ''; // Clear

        let keys = measurementMapping[subCatId] || [];

        if (keys.length === 0) {
            empty.innerHTML = 'No measurement fields required for this service.';
            empty.style.display = 'block';
            grid.style.display = 'none';
            return;
        }

        keys.forEach(key => {
            const div = document.createElement('div');
            div.className = 'form-group';
            div.innerHTML = `
                <label class="form-label">${key}</label>
                <input type="text" name="measurements[${key}]" class="form-control" placeholder="0.0">
             `;
            grid.appendChild(div);
        });
    }

    function calculateTotal() {
        let base = parseFloat(document.getElementById('base_price').value) || 0;
        let extra = parseFloat(document.getElementById('extra_charges').value) || 0;

        let servicesTotal = 0;
        document.querySelectorAll('input[name="selected_services[]"]:checked').forEach(cb => {
            servicesTotal += parseFloat(cb.dataset.price) || 0;
        });

        let total = base + extra + servicesTotal;
        document.getElementById('total_amount').innerText = '₹ ' + total.toFixed(2);
    }

    function toggleNote(el, note) {
        el.classList.toggle('active');
        const textarea = document.getElementById('design-notes');
        let currentText = textarea.value.trim();
        if (el.classList.contains('active')) {
            // Add note
            if (currentText.length > 0) {
                textarea.value = currentText + ", " + note;
            } else {
                textarea.value = note;
            }
        } else {
            // Remove note
            let regex = new RegExp(",?\\s*" + note + "|\\b" + note + "\\s*,?", "gi");
            let newText = currentText.replace(regex, "").trim();
            // Clean up leading/trailing commas
            newText = newText.replace(/^,|,$/g, "").trim();
            textarea.value = newText;
        }
    }
    function updateUnitStyle(unit) {
        if (unit === 'CMS') {
            document.getElementById('unit-cms-label').classList.add('unit-active');
            document.getElementById('unit-inch-label').classList.remove('unit-active');
        } else {
            document.getElementById('unit-inch-label').classList.add('unit-active');
            document.getElementById('unit-cms-label').classList.remove('unit-active');
        }
    }

    function previewImages(input, previewId) {

        const preview = document.getElementById(previewId);

        const icon = preview.querySelector('i');
        const text = preview.querySelector('span');

        let oldImage = preview.querySelector('.preview-img');

        if (oldImage) {
            oldImage.remove();
        }

        if (input.files && input.files[0]) {

            const reader = new FileReader();

            reader.onload = function (e) {

                const img = document.createElement('img');

                img.src = e.target.result;

                img.classList.add('preview-img');

                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                img.style.borderRadius = '12px';
                img.style.position = 'absolute';
                img.style.top = '0';
                img.style.left = '0';
                img.style.background = '#fff';

                preview.style.height = '110px';

                preview.appendChild(img);

                if (icon) icon.style.display = 'none';

                if (text) text.style.display = 'none';
            };

            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
<script>

    document.addEventListener('DOMContentLoaded', function () {

        const paymentMode = document.querySelector('[name="advance_payment_mode"]');

        const transactionGroup = document.getElementById('transactionReferenceGroup');

        function toggleTransactionReference() {

            if (
                paymentMode.value !== '' &&
                paymentMode.value !== 'Cash'
            ) {

                transactionGroup.style.display = 'block';

            } else {

                transactionGroup.style.display = 'none';
            }
        }

        paymentMode.addEventListener('change', toggleTransactionReference);

        toggleTransactionReference();

    });

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeRadios = document.querySelectorAll('input[name="order_type"]');
        const outsourceGroup = document.getElementById('outsource-credit-group');
        const outsourceInput = document.getElementById('outsource_credit');

        function toggleOutsourceCredit() {
            const selected = document.querySelector('input[name="order_type"]:checked').value;

            if (selected === 'outsource') {
                outsourceGroup.style.display = 'block';
            } else {
                outsourceGroup.style.display = 'none';
            }
        }

        typeRadios.forEach(radio => {
            radio.addEventListener('change', toggleOutsourceCredit);
        });

        toggleOutsourceCredit();
    });
</script>
<?php include 'includes/footer.php'; ?>