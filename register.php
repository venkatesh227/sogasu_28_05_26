<?php
session_start();
require_once 'includes/db.php';

$fieldErrors = [];
$success = false;
if (isset($_SESSION['register_success'])) {
    $success = true;
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $address = trim($_POST['address'] ?? '');

    /*
PHP VALIDATIONS
*/

    // First Name
    if ($first_name === '') {
        $fieldErrors['first_name'] = "First name is required";
    } elseif (!preg_match('/^[a-zA-Z ]+$/', $first_name)) {
        $fieldErrors['first_name'] = "Only letters and spaces allowed";
    }

    // Last Name (optional but if entered validate)
    if ($last_name !== '' && !preg_match('/^[a-zA-Z ]+$/', $last_name)) {
        $fieldErrors['last_name'] = "Only letters and spaces allowed";
    }

    // Phone
    if ($phone === '') {
        $fieldErrors['phone'] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $fieldErrors['phone'] = "Phone number must be exactly 10 digits";
    }

    // Email
    if ($email === '') {
        $fieldErrors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = "Invalid email format";
    }

    // Password
    if ($password === '') {
        $fieldErrors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $fieldErrors['password'] = "Password must be at least 6 characters";
    }
    // Address
    if ($address === '') {
        $fieldErrors['address'] = "Address is required";
    }
    if (empty($fieldErrors)) {
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);

        if ($checkEmail->fetch()) {
            $fieldErrors['email'] = "Email already exists";
        }
    }

    if (empty($fieldErrors)) {
        $checkPhone = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $checkPhone->execute([$phone]);

        if ($checkPhone->fetch()) {
            $fieldErrors['phone'] = "Mobile number already exists";
        }
    }
    /*
    INSERT INTO TABLES
    */
    if (empty($fieldErrors)) {
        try {
            $pdo->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $username = trim($first_name . ' ' . $last_name);

            /*
            USERS TABLE
            */
            $stmt = $pdo->prepare("
                INSERT INTO users
                (username, mobile, email, password, role, status, is_registered)
                VALUES (?, ?, ?, ?, 'employee', 1, 1)
            ");

            $stmt->execute([
                $username,
                $phone,
                $email,
                $hashedPassword
            ]);

            $user_id = $pdo->lastInsertId();

            /*
            EMPLOYEES TABLE
            */
            $stmt2 = $pdo->prepare("
                INSERT INTO employees
                (
                    user_id,
                    first_name,
                    last_name,
                    phone,
                    email,
                    address,
                    employee_type
                )
                VALUES (?, ?, ?, ?, ?, ?, 'inhouse')
            ");

            $stmt2->execute([
                $user_id,
                $first_name,
                $last_name ?: null,
                $phone,
                $email,
                $address ?: null
            ]);

            $pdo->commit();

            $_SESSION['register_success'] = true;
            header("Location: register.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $fieldErrors['general'] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sogasu</title>

    <link rel="stylesheet" href="css/global.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a237e 0%, #0d1245 100%);
            padding: 1rem;
        }

        .register-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 550px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            box-sizing: border-box;
        }

        .register-btn {
            width: 100%;
            padding: 1rem;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="register-card">
        <h1 style="text-align:center;">Register</h1>
        <p style="text-align:center;">Create Employee Account</p>
        <?php if (!empty($fieldErrors['general'])): ?>
            <div style="color:red; margin-bottom:15px;">
                <?= $fieldErrors['general'] ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" placeholder="First Name"
                    value="<?= htmlspecialchars($first_name ?? '') ?>">

                <?php if (!empty($fieldErrors['first_name'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['first_name'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" placeholder="Last Name"
                    value="<?= htmlspecialchars($last_name ?? '') ?>">

                <?php if (!empty($fieldErrors['last_name'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['last_name'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" placeholder="Phone Number"
                    value="<?= htmlspecialchars($phone ?? '') ?>"
                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                <?php if (!empty($fieldErrors['phone'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['phone'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Email"
                    value="<?= htmlspecialchars($email ?? '') ?>">
                <?php if (!empty($fieldErrors['email'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['email'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Password">
                <?php if (!empty($fieldErrors['password'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['password'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" placeholder="Address"
                    rows="4"><?= htmlspecialchars($address ?? '') ?></textarea>
                <?php if (!empty($fieldErrors['address'])): ?>
                    <small style="color:red;">
                        <?= $fieldErrors['address'] ?>
                    </small>
                <?php endif; ?>
            </div>

            <button type="submit" class="register-btn">Register</button>
        </form>
        <div style="text-align:center; margin-top:15px;">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
    <?php if ($success): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Registration Successful!',
                text: 'Employee account created successfully.',
                confirmButtonText: 'Go to Home',
                confirmButtonColor: '#1a237e'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>
    <?php endif; ?>
</body>

</html>