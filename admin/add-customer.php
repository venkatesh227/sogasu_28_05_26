<?php
session_start();
include '../includes/db.php';
$errors = [];
$old = [];
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        die("Customer not found");
    }

    $old = $customer; // THIS IS THE KEY LINE
    // GET STATUS FROM USERS TABLE
    $userStmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $userStmt->execute([$customer['user_id']]);
    $user = $userStmt->fetch();

    $old['status'] = $user['status'] ?? 1;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // if (!isset($_SESSION['user_id'])) {
    //     die("Unauthorized access");
    // }

    $current_user_id = $_SESSION['user_id'] ?? 1;
    // Sanitize inputs
    $old['first_name'] = trim($_POST['first_name'] ?? '');
    $old['last_name'] = trim($_POST['last_name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['secondary_phone'] = trim($_POST['secondary_phone'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');
    $old['area'] = trim($_POST['area'] ?? '');
    $old['city'] = trim($_POST['city'] ?? '');
    $old['branch'] = $_POST['branch'] ?? '';
    $old['source'] = $_POST['source'] ?? '';
    $old['notes'] = trim($_POST['notes'] ?? '');
    $old['status'] = $_POST['status'] ?? 1;
    $password = $_POST['password'] ?? '';

    // ===== VALIDATIONS =====

    // First Name
    if ($old['first_name'] == '') {
        $errors['first_name'] = "First name is required";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $old['first_name'])) {
        $errors['first_name'] = "Only letters allowed";
    }
    if ($old['last_name'] != '' && !preg_match("/^[a-zA-Z ]+$/", $old['last_name'])) {
        $errors['last_name'] = "Only letters allowed";
    }

    // Phone Number
    if ($old['phone'] == '') {
        $errors['phone'] = "Phone number is required";
    } elseif (!ctype_digit($old['phone'])) {
        $errors['phone'] = "Only numbers are allowed";
    } elseif (strlen($old['phone']) != 10) {
        $errors['phone'] = "Must be exactly 10 digits";
    } elseif (!preg_match("/^[6-9]/", $old['phone'])) {
        $errors['phone'] = "Must start with 6, 7, 8 or 9";
    }

    // Secondary Phone (optional but validate if present)
    if ($old['secondary_phone'] != '') {

        if (!ctype_digit($old['secondary_phone'])) {
            $errors['secondary_phone'] = "Only numbers are allowed";
        } elseif (strlen($old['secondary_phone']) != 10) {
            $errors['secondary_phone'] = "Must be exactly 10 digits";
        } elseif (!preg_match("/^[6-9]/", $old['secondary_phone'])) {
            $errors['secondary_phone'] = "Must start with 6, 7, 8 or 9";
        }
    }

    // Email (optional but validate)
    if ($old['email'] != '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    // Address
    if ($old['address'] == '') {
        $errors['address'] = "Address is required";
    }
    if ($old['area'] != '' && !preg_match("/^[a-zA-Z0-9 ,.-]+$/", $old['area'])) {
        $errors['area'] = "Invalid area format";
    }

    // City
    if ($old['city'] == '') {
        $errors['city'] = "City is required";
    }
    if (!$id && $password == '') {
        $errors['password'] = "Password is required";
    }

    if ($password != '' && strlen($password) < 6) {
        $errors['password'] = "Minimum 6 characters required";
    }

    if (empty($errors)) {

        if ($id) {
            // ===== UPDATE =====
            $stmt = $pdo->prepare("UPDATE customers SET 
            first_name=?, last_name=?, phone=?, secondary_phone=?, email=?, 
            address=?, area=?, city=?, branch=?, source=?, notes=?, status=?, updated_at=NOW(), updated_by=?
            WHERE id=?");

            if (
                $stmt->execute([
                    $old['first_name'],
                    $old['last_name'],
                    $old['phone'],
                    $old['secondary_phone'],
                    $old['email'],
                    $old['address'],
                    $old['area'],
                    $old['city'],
                    $old['branch'],
                    $old['source'],
                    $old['notes'],
                    $old['status'],
                    $current_user_id,
                    $id
                ])
            ) {

                //  ALSO UPDATE USERS TABLE
                if ($password != '') {

                    $updateUser = $pdo->prepare("
                        UPDATE users 
                        SET username=?, email=?, mobile=?, password=?, status=?, updated_at=NOW()
                        WHERE id=?
                    ");

                    $updateUser->execute([
                        $old['first_name'] . ' ' . $old['last_name'],
                        $old['email'],
                        $old['phone'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $old['status'],
                        $customer['user_id']
                    ]);

                } else {

                    $updateUser = $pdo->prepare("
                            UPDATE users 
                            SET username=?, email=?, mobile=?, status=?, updated_at=NOW()
                            WHERE id=?
                    ");

                    $updateUser->execute([
                        $old['first_name'] . ' ' . $old['last_name'],
                        $old['email'],
                        $old['phone'],
                        $old['status'],
                        $customer['user_id']
                    ]);

                }

                $_SESSION['success'] = "Customer updated successfully!";
                header("Location: customers.php");
                exit;

            } else {
                $errors['db'] = "Update failed";
            }


        } else {

            // ===== INSERT =====

            $check = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
            $check->execute([$old['phone']]);

            if ($check->rowCount() > 0) {
                $errors['phone'] = "Customer with this phone already exists";
            } else {

                // CHECK DUPLICATE USER
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
                $checkUser->execute([$old['phone']]);

                if ($checkUser->rowCount() > 0) {
                    $errors['phone'] = "User already exists";
                } else {

                    $username = $old['first_name'] . ' ' . $old['last_name'];
                    $password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'customer';
                    $status = $old['status'];

                    // PREPARE BOTH QUERIES
                    $userStmt = $pdo->prepare("
                        INSERT INTO users (username, email, mobile, password, role, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $customerStmt = $pdo->prepare("
                        INSERT INTO customers 
                        (first_name, last_name, phone, secondary_phone, email, address, area, city, branch, source, notes, user_id, status, created_at, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                        ");

                    $pdo->beginTransaction();

                    try {

                        // INSERT USER
                        $userStmt->execute([
                            $username,
                            $old['email'],
                            $old['phone'],
                            $password,
                            $role,
                            $status
                        ]);

                        $user_id = $pdo->lastInsertId();

                        if (!$user_id) {
                            throw new Exception("User creation failed");
                        }

                        // INSERT CUSTOMER
                        $customerStmt->execute([
                            $old['first_name'],
                            $old['last_name'],
                            $old['phone'],
                            $old['secondary_phone'],
                            $old['email'],
                            $old['address'],
                            $old['area'],
                            $old['city'],
                            $old['branch'],
                            $old['source'],
                            $old['notes'],
                            $user_id,
                            $status,
                            $current_user_id
                        ]);

                        $pdo->commit();

                        $_SESSION['success'] = "Customer registered successfully!";
                        header("Location: customers.php");
                        exit;

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errors['db'] = $e->getMessage();
                    }
                }
            }


        }
    }
}
$branchStmt = $pdo->query("SELECT branch_name FROM branches WHERE status = 1");
$branches = $branchStmt->fetchAll();

$pageTitle = "Add New Customer - Sogasu";
$activePage = "customers";
include 'includes/header.php';
?>


<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>

        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">
                    <?= isset($id) ? 'Edit' : 'Add New' ?> Customer
                </h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Register or update client profile details</p>
            </div>
            <button class="btn btn-light" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                <i class="ri-arrow-left-line"></i> Back to List
            </button>
        </div>
        <form method="POST" action="" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <input type="hidden" name="role" value="customer">

            <!-- Left Column: Personal & Address -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <div class="table-container" style="margin-top: 0;">
                    <h3
                        style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        Personal Information</h3>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">First Name <span style="color:red">*</span></label>
                            <input type="text" name="first_name" placeholder="e.g. Rashmi" class="form-control"
                                value="<?= $old['first_name'] ?? '' ?>">
                            <?php if (isset($errors['first_name'])): ?>
                                <small style="color:red;">
                                    <?= $errors['first_name'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" placeholder="e.g. Kumar" class="form-control"
                                value="<?= $old['last_name'] ?? '' ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-top: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Phone Number <span style="color:red">*</span></label>
                            <input type="text" name="phone" placeholder="10-digit mobile number" class="form-control"
                                value="<?= $old['phone'] ?? '' ?>">
                            <?php if (isset($errors['phone'])): ?>
                                <small style="color:red;">
                                    <?= $errors['phone'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Secondary Phone</label>
                            <input type="text" name="secondary_phone" placeholder="Alternate Number"
                                class="form-control" value="<?= $old['secondary_phone'] ?? '' ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-top: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" placeholder="name@example.com" class="form-control"
                                value="<?= $old['email'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Password
                                <?= isset($id) ? '(Optional)' : '<span style="color:red">*</span>' ?></label>
                            <input type="password" name="password" class="form-control"
                                placeholder="<?= isset($id) ? 'Leave blank to keep old' : 'Set login password' ?>">
                            <?php if (isset($errors['password'])): ?>
                                <small style="color:red;">
                                    <?= $errors['password'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="table-container" style="margin-top: 0;">
                    <h3
                        style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        Address Details</h3>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Street Address <span style="color:red">*</span></label>
                        <input type="text" placeholder="House/Flat No, Street Name" name="address" class="form-control"
                            value="<?= $old['address'] ?? '' ?>">
                        <?php if (isset($errors['address'])): ?>
                            <small style="color:red;">
                                <?= $errors['address'] ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Area / Locality</label>
                            <input type="text" name="area" class="form-control" placeholder="e.g. Jayanagar 4th Block"
                                value="<?= $old['area'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City <span style="color:red">*</span></label>
                            <input type="text" name="city" placeholder="e.g. Bangalore" class="form-control"
                                value="<?= $old['city'] ?? '' ?>">
                            <?php if (isset($errors['city'])): ?>
                                <small style="color:red;">
                                    <?= $errors['city'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Preferences & Actions -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <div class="table-container" style="margin-top: 0;">
                    <h3
                        style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        Preferences</h3>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Preferred Branch</label>
                        <select name="branch" class="form-select">
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['branch_name'] ?>" <?= ($old['branch'] ?? '') == $branch['branch_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Customer Source</label>
                        <select name="source" class="form-select">
                            <option <?= ($old['source'] ?? '') == 'Walk-in' ? 'selected' : '' ?>>Walk-in</option>
                            <option <?= ($old['source'] ?? '') == 'Referral' ? 'selected' : '' ?>>Referral</option>
                            <option <?= ($old['source'] ?? '') == 'Instagram/Social' ? 'selected' : '' ?>>Instagram/Social
                            </option>
                            <option <?= ($old['source'] ?? '') == 'Advertisement' ? 'selected' : '' ?>>Advertisement
                            </option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="1" <?= ($old['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($old['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Internal Notes</label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="Any specific requirements..."><?= $old['notes'] ?? '' ?></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="table-container" style="margin-top: 0; background: #f8fafc;">
                    <button type="submit" class="btn btn-primary"
                        style="width: 100%; padding: 12px; font-weight: 700; border-radius: 8px; border: none; background: #4f46e5; color: white; cursor: pointer; margin-bottom: 1rem;">
                        <?= isset($id) ? 'UPDATE CUSTOMER' : 'SAVE CUSTOMER' ?>
                    </button>
                    <button type="button" class="btn" onclick="history.back()"
                        style="width: 100%; padding: 12px; font-weight: 700; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer;">
                        CANCEL
                    </button>
                </div>

            </div>

        </form>
    </div>


    <style>
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
    </style>

    <?php include 'includes/footer.php'; ?>