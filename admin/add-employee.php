<?php
ob_start();
session_start();
include '../includes/db.php';

$branchStmt = $pdo->query("SELECT branch_name FROM branches WHERE status='active' AND deleted_at IS NULL ORDER BY branch_name ASC");
$branchList = $branchStmt->fetchAll();

$jobRoleStmt = $pdo->query("SELECT role_name FROM job_roles WHERE status='active' AND is_deleted=0 ORDER BY role_name ASC");
$jobRoleList = $jobRoleStmt->fetchAll();

$payCycleStmt = $pdo->query("SELECT cycle_name FROM pay_cycles WHERE status='active' AND is_deleted=0 ORDER BY cycle_name ASC");
$payCycleList = $payCycleStmt->fetchAll();

$shiftStmt = $pdo->query("SELECT id, name FROM shift_types ORDER BY name ASC");
$shiftList = $shiftStmt->fetchAll();

$id = $_GET['id'] ?? null;
$pageTitle = $id ? "Edit Employee - Sogasu" : "Add New Employee - Sogasu";
$activePage = "add-employee";
$errors = [];
$old = [];

if ($id) {
    $stmt = $pdo->prepare("
    SELECT * FROM employees 
    WHERE id = ?
    AND is_deleted = 0
");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();

    if (!$employee)
        die("Employee not found");

    $old = $employee;
    $old['supervisor'] = $employee['supervisor_id'] ?? '';
    // GET STATUS FROM USERS TABLE
    $userStmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $userStmt->execute([$employee['user_id']]);
    $user = $userStmt->fetch();

    $old['status'] = $user['status'] ?? 1;
}
$selectedBranch = $_POST['branch'] ?? ($old['branch'] ?? '');

if (!empty($selectedBranch)) {

    $supervisorStmt = $pdo->prepare("
        SELECT id, first_name, last_name, job_role
        FROM employees
        WHERE status = 1
        AND job_role = 'Supervisor'
        AND branch = ?
        ORDER BY first_name ASC
    ");

    $supervisorStmt->execute([$selectedBranch]);

} else {

    $supervisorStmt = $pdo->prepare("
        SELECT id, first_name, last_name, job_role
        FROM employees
        WHERE status = 1
        AND job_role = 'Supervisor'
        ORDER BY first_name ASC
    ");

    $supervisorStmt->execute();
}

$supervisorList = $supervisorStmt->fetchAll();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $current_user_id = $_SESSION['user_id'] ?? 1;
    $employee_type = $_POST['employee_type'] ?? 'inhouse';
    $old['employee_type'] = $employee_type;

    // GET DATA
    $old['first_name'] = trim($_POST['first_name'] ?? '');
    $old['last_name'] = trim($_POST['last_name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');

    $old['job_role'] = $_POST['job_role'] ?? '';
    $old['joining_date'] = $_POST['joining_date'] ?? '';
    $old['branch'] = $_POST['branch'] ?? '';
    $old['employment_status'] = $_POST['employment_status'] ?? '';
    $old['supervisor'] = $_POST['supervisor'] ?? '';

    $old['payment_model'] = $_POST['payment_model'] ?? '';
    $old['base_salary'] = $_POST['base_salary'] ?? 0;
    $old['pay_cycle'] = $_POST['pay_cycle'] ?? '';
    $old['default_shift_id'] = $_POST['default_shift_id'] ?? null;
    $old['status'] = $_POST['status'] ?? 1;
    $password = $_POST['password'] ?? '';
    $role = 'employee';
    $employee_type = $_POST['employee_type'] ?? 'inhouse';

    // ===== VALIDATIONS =====

    if (trim($old['first_name']) == '') {
        $errors['first_name'] = "First name is required";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $old['first_name'])) {
        $errors['first_name'] = "Only letters allowed";
    }
    if ($old['last_name'] != '' && !preg_match("/^[a-zA-Z ]+$/", $old['last_name'])) {
        $errors['last_name'] = "Only letters allowed";
    }
    if ($old['phone'] == '') {
        $errors['phone'] = "Phone number is required";
    } elseif (!ctype_digit($old['phone'])) {
        $errors['phone'] = "Only numbers are allowed";
    } elseif (strlen($old['phone']) != 10) {
        $errors['phone'] = "Must be exactly 10 digits";
    } elseif (!preg_match("/^[6-9]/", $old['phone'])) {
        $errors['phone'] = "Must start with 6, 7, 8 or 9";
    }
    if (empty($errors) && $id) {
        $checkPhone = $pdo->prepare("
        SELECT id 
        FROM employees
        WHERE phone = ?
        AND id != ?
        AND is_deleted = 0
    ");

        $checkPhone->execute([$old['phone'], $id]);

        if ($checkPhone->fetch()) {
            $errors['phone'] = "Phone number already exists";
        }
    }
    if ($old['email'] != '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    if ($id && $old['email'] != '') {
        $checkEmail = $pdo->prepare("
        SELECT id FROM employees
        WHERE email = ? AND id != ? AND is_deleted = 0
    ");
        $checkEmail->execute([$old['email'], $id]);

        if ($checkEmail->fetch()) {
            $errors['email'] = "Email already exists";
        }
    }
    if ($old['address'] == '') {
        $errors['address'] = "Address is required";
    }

    if ($old['joining_date'] == '') {
        $errors['joining_date'] = "Joining date is required";
    } elseif ($old['joining_date'] > date('Y-m-d')) {
        $errors['joining_date'] = "Future dates not allowed";
    }
    if ($employee_type === 'inhouse') {

        if ($old['base_salary'] === '') {

            $errors['base_salary'] = 'Base salary is required';

        } elseif (!is_numeric($old['base_salary']) || $old['base_salary'] < 0) {

            $errors['base_salary'] = 'Enter valid salary';

        }

    }
    if ($old['job_role'] == '') {
        $errors['job_role'] = "Job role is required";
    }
    if ($old['job_role'] === 'Supervisor' && !empty($old['supervisor'])) {
        $errors['supervisor'] = "Supervisor cannot report to another supervisor";
    }

    if (!empty($old['supervisor']) && !empty($old['branch'])) {

        $supervisorCheck = $pdo->prepare("
        SELECT id
        FROM employees
        WHERE id = ?
        AND branch = ?
        AND job_role = 'Supervisor'
        AND status = 1
    ");

        $supervisorCheck->execute([
            $old['supervisor'],
            $old['branch']
        ]);

        if (!$supervisorCheck->fetch()) {
            $errors['supervisor'] = "Selected supervisor must belong to same branch";
        }
    }

    if ($old['branch'] == '') {
        $errors['branch'] = "Branch is required";
    }

    if ($old['employment_status'] == '') {
        $errors['employment_status'] = "Employment status is required";
    }

    if ($employee_type === 'inhouse' && empty($old['payment_model'])) {
        $errors['payment_model'] = 'Payment model is required';
    }

    if ($old['pay_cycle'] == '') {
        $errors['pay_cycle'] = "Pay cycle is required";
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

            $stmt = $pdo->prepare("
                UPDATE employees SET 
                first_name=?, last_name=?, phone=?, email=?, address=?,
                job_role=?, supervisor_id=?, joining_date=?, branch=?, default_shift_id=?, employment_status=?,
                payment_model=?, base_salary=?, pay_cycle=?,employee_type=?,
                status=?, updated_at=NOW(), updated_by=?
                WHERE id=?
            ");

            if (
                $stmt->execute([
                    $old['first_name'],
                    $old['last_name'],
                    $old['phone'],
                    $old['email'],
                    $old['address'],
                    $old['job_role'],
                    $old['supervisor'] ?: null,
                    $old['joining_date'],
                    $old['branch'],
                    $old['default_shift_id'],
                    $old['employment_status'],
                    $old['payment_model'],
                    $old['base_salary'],
                    $old['pay_cycle'],
                    $employee_type,
                    $old['status'],
                    $current_user_id,
                    $id
                ])
            ) {

                // update user
                // update user
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
                        $employee['user_id']
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
                        $employee['user_id']
                    ]);

                }

                $_SESSION['success'] = "updated";

                if ($employee_type === 'outsource') {
                    header("Location: outsource_employees.php");
                } else {
                    header("Location: employees.php");
                }
                exit;
            }

        } else {
            $checkEmp = $pdo->prepare("SELECT id FROM employees WHERE phone=?");
            $checkEmp->execute([$old['phone']]);

            if ($checkEmp->rowCount() > 0) {
                $errors['phone'] = "Employee already exists";
            } else {

                $checkUser = $pdo->prepare("SELECT id FROM users WHERE mobile=?");
                $checkUser->execute([$old['phone']]);

                if ($checkUser->rowCount() > 0) {
                    $errors['phone'] = "User exists";
                } else {

                    $pdo->beginTransaction();

                    try {
                        if ($employee_type === 'outsource') {
                            $old['payment_model'] = null;
                            $old['base_salary'] = 0;
                        }

                        $userStmt = $pdo->prepare("
                        INSERT INTO users (username,email,mobile,password,role,status,created_at)
                        VALUES (?,?,?,?,?,?,NOW())
                       ");

                        $userStmt->execute([
                            $old['first_name'] . ' ' . $old['last_name'],
                            $old['email'],
                            $old['phone'],
                            password_hash($password, PASSWORD_DEFAULT),
                            $role,
                            $old['status']
                        ]);

                        $user_id = $pdo->lastInsertId();

                        $empStmt = $pdo->prepare("
                            INSERT INTO employees 
                            (user_id,first_name,last_name,phone,email,address,
                            job_role,supervisor_id,joining_date,branch,default_shift_id,employment_status,
                            payment_model,base_salary,pay_cycle,status,created_at,created_by,employee_type)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?)
                        ");

                        $empStmt->execute([
                            $user_id,
                            $old['first_name'],
                            $old['last_name'],
                            $old['phone'],
                            $old['email'],
                            $old['address'],
                            $old['job_role'],
                            $old['supervisor'] ?: null,
                            $old['joining_date'],
                            $old['branch'],
                            $old['default_shift_id'],
                            $old['employment_status'],
                            $old['payment_model'],
                            $old['base_salary'],
                            $old['pay_cycle'],
                            $old['status'],
                            $current_user_id,
                            $employee_type
                        ]);

                        $pdo->commit();

                        $_SESSION['success'] = "Employee Created Successfully";

                        if ($employee_type === 'outsource') {
                            header("Location: outsource_employees.php");
                        } else {
                            header("Location: employees.php");
                        }
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
include 'includes/header.php'; ?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                    <?= $id ? 'Edit Employee' : 'Add New Employee' ?>
                </h2>
                <p class="text-muted">Enter staff details and salary configuration</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <input type="hidden" name="role" value="employee">

        <!-- Left Column: Personal & Job Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <!-- Personal Info -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">

                    <h3 style="font-size:1.1rem; font-weight:600; color:#1e293b; margin:0;">
                        Personal Information
                    </h3>

                    <div style="display:flex; align-items:center; gap:15px;">

                        <span style="font-weight:600; color:#475569;">
                            Type :
                        </span>

                        <label style="display:flex; align-items:center; gap:5px; margin:0; cursor:pointer;">
                            <input type="radio" name="employee_type" value="inhouse" <?= (($old['employee_type'] ?? 'inhouse') == 'inhouse') ? 'checked' : '' ?>>
                            Inhouse
                        </label>

                        <label style="display:flex; align-items:center; gap:5px; margin:0; cursor:pointer;">
                            <input type="radio" name="employee_type" value="outsource" <?= (($old['employee_type'] ?? '') == 'outsource') ? 'checked' : '' ?>>
                            Outsource
                        </label>

                    </div>

                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">First Name <span style="color:red">*</span></label>
                        <input type="text" name="first_name" class="form-control" maxlength="50"
                            placeholder="e.g. Sushmita" value="<?= $old['first_name'] ?? '' ?>">
                        <?php if (isset($errors['first_name'])): ?>
                            <small style="color:red"><?= $errors['first_name'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" maxlength="50" placeholder="e.g. Kumar"
                            value="<?= $old['last_name'] ?? '' ?>">
                        <?php if (isset($errors['last_name'])): ?>
                            <small style="color:red"><?= $errors['last_name'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Phone Number <span style="color:red">*</span></label>
                        <input type="tel" name="phone" class="form-control" maxlength="10"
                            placeholder="10-digit mobile number" value="<?= $old['phone'] ?? '' ?>">
                        <?php if (isset($errors['phone'])): ?>
                            <small style="color:red"><?= $errors['phone'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Optional)</label>
                        <input type="email" name="email" class="form-control" maxlength="50"
                            placeholder="name@example.com" value="<?= $old['email'] ?? '' ?>">
                        <?php if (isset($errors['email'])): ?>
                            <small style="color:red"><?= $errors['email'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">
                        Password <span style="color:red">*</span> <?= isset($id) ? '(Leave blank to keep old)' : '' ?>
                    </label>
                    <input type="password" name="password" class="form-control">

                    <?php if (isset($errors['password'])): ?>
                        <small style="color:red"><?= $errors['password'] ?></small>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Address <span style="color:red">*</span></label>
                        <textarea class="form-control" name="address" rows="3"
                            placeholder="Residential address"><?= $old['address'] ?? '' ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <small style="color:red"><?= $errors['address'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Job Details -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Work Details</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label">Job Role <span style="color:red">*</span></label>
                            <a href="add-job-role.php"
                                style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 500;">+
                                Create New</a>
                        </div>
                        <select class="form-select" name="job_role" id="job_role_select"
                            onchange="toggleSupervisorField()">
                            <option value="">Select Job Role</option>
                            <?php foreach ($jobRoleList as $role): ?>
                                <option value="<?= htmlspecialchars($role['role_name']) ?>" <?= ($old['job_role'] ?? '') == $role['role_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['job_role'])): ?>
                            <small style="color:red"><?= $errors['job_role'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Joining Date <span style="color:red">*</span></label>
                        <input type="date" class="form-control" name="joining_date" max="<?= date('Y-m-d') ?>"
                            value="<?= $old['joining_date'] ?? '' ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Branch <span style="color:red">*</span></label>
                        <select class="form-select" name="branch">
                            <option value="">Select Branch</option>
                            <?php foreach ($branchList as $b): ?>
                                <option value="<?= htmlspecialchars($b['branch_name']) ?>" <?= ($old['branch'] ?? '') == $b['branch_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['branch'])): ?>
                            <small style="color:red"><?= $errors['branch'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employment Status <span style="color:red">*</span></label>
                        <select class="form-select" name="employment_status">
                            <option <?= ($old['employment_status'] ?? '') == 'Full Time' ? 'selected' : '' ?>>Full Time
                            </option>
                            <option <?= ($old['employment_status'] ?? '') == 'Part Time' ? 'selected' : '' ?>>Part Time
                            </option>
                            <option <?= ($old['employment_status'] ?? '') == 'Contract' ? 'selected' : '' ?>>Contract
                            </option>
                        </select>
                        <?php if (isset($errors['employment_status'])): ?>
                            <small style="color:red"><?= $errors['employment_status'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group" id="supervisor_group">
                        <label class="form-label">Supervisor (Reporting To)</label>
                        <select class="form-select" name="supervisor" id="supervisor_select">
                            <option value="">Select Supervisor</option>
                            <?php foreach ($supervisorList as $supervisor): ?>
                                <option value="<?= $supervisor['id'] ?>" <?= ($old['supervisor'] ?? '') == $supervisor['id'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']) ?>
                                    (<?= htmlspecialchars($supervisor['job_role']) ?>)

                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['supervisor'])): ?>
                            <small style="color:red"><?= $errors['supervisor'] ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default Work Shift</label>
                        <select class="form-select" name="default_shift_id">
                            <option value="">-- No Default Shift --</option>
                            <?php foreach ($shiftList as $shift): ?>
                                <option value="<?= $shift['id'] ?>" <?= ($old['default_shift_id'] ?? '') == $shift['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($shift['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem;">This shift will be
                            auto-assigned during roster creation.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Salary & Documents -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <!-- Salary Config -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Salary Structure
                </h3>

                <div class="form-group" id="paymentModelWrapper" style="margin-bottom: 1rem;">
                    <label class="form-label">Payment Model <span style="color:red">*</span></label>
                    <select class="form-select" name="payment_model">
                        <option <?= ($old['payment_model'] ?? '') == 'Fixed Monthly' ? 'selected' : '' ?>>Fixed Monthly
                        </option>
                        <option <?= ($old['payment_model'] ?? '') == 'Piece Rate (Per Garment)' ? 'selected' : '' ?>>Piece
                            Rate (Per Garment)</option>
                        <option <?= ($old['payment_model'] ?? '') == 'Fixed + Incentives' ? 'selected' : '' ?>>Fixed +
                            Incentives</option>
                    </select>
                    <?php if (isset($errors['payment_model'])): ?>
                        <small style="color:red"><?= $errors['payment_model'] ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group" id="baseSalaryWrapper" style="margin-bottom: 1rem;">
                    <label class="form-label">Base Salary / Rate <span style="color:red">*</span></label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b;">₹</span>
                        <input type="number" name="base_salary" class="form-control" style="padding-left: 2rem;"
                            placeholder="0.00" value="<?= $old['base_salary'] ?? '' ?>">
                        <?php if (isset($errors['base_salary'])): ?>
                            <small style="color:red"><?= $errors['base_salary'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Pay Cycle <span style="color:red">*</span></label>
                    <select class="form-select" name="pay_cycle">
                        <option value="">Select Pay Cycle</option>
                        <?php foreach ($payCycleList as $pc): ?>
                            <option value="<?= htmlspecialchars($pc['cycle_name']) ?>" <?= ($old['pay_cycle'] ?? '') == $pc['cycle_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pc['cycle_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['pay_cycle'])): ?>
                        <small style="color:red"><?= $errors['pay_cycle'] ?></small>
                    <?php endif; ?>
                </div>

            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="1" <?= ($old['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ($old['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Finish</h3>
                <button class="btn btn-primary w-full"
                    style="justify-content: center; width: 100%; margin-bottom: 1rem;"><?= isset($id) ? 'Update Employee' : 'Create Employee Record' ?></button>
                <button type="button" class="btn w-full"
                    style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
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
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success'] == "updated" ? "Employee updated successfully" : "Employee added successfully" ?>'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

<script>
    function toggleSupervisorField() {
        const jobRole = document.getElementById('job_role_select').value;
        const supervisorGroup = document.getElementById('supervisor_group');
        const supervisorSelect = document.getElementById('supervisor_select');

        if (jobRole === 'Supervisor') {
            supervisorGroup.style.display = 'none';
            supervisorSelect.value = ''; // Reset selection
        } else {
            supervisorGroup.style.display = 'flex';
        }
    }

    // Run on page load
    document.addEventListener('DOMContentLoaded', toggleSupervisorField);
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const paymentModelWrapper = document.getElementById('paymentModelWrapper');
        const baseSalaryWrapper = document.getElementById('baseSalaryWrapper');

        function toggleSalaryFields() {

            const employeeType =
                document.querySelector('input[name="employee_type"]:checked').value;

            if (employeeType === 'outsource') {

                paymentModelWrapper.style.display = 'none';
                baseSalaryWrapper.style.display = 'none';

            } else {

                paymentModelWrapper.style.display = 'block';
                baseSalaryWrapper.style.display = 'block';

            }
        }

        document.querySelectorAll('input[name="employee_type"]').forEach(function (radio) {
            radio.addEventListener('change', toggleSalaryFields);
        });

        toggleSalaryFields();
    });
</script>
<?php
include 'includes/footer.php';
ob_end_flush();
?>