<?php
session_start();
require '../includes/db.php';

// Prevent already logged-in admin from seeing login again
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'super_admin' || isset($_SESSION['active_role'])) {
        header("Location: dashboard.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // Dynamically resolve the active role
        $active_role = $user['role'];
        if ($user['role'] === 'employee') {
            $empStmt = $pdo->prepare("SELECT job_role FROM employees WHERE user_id = ?");
            $empStmt->execute([$user['id']]);
            $job_role = $empStmt->fetchColumn();
            if ($job_role) {
                $active_role = $job_role;
            }
        }

        // Verify admin access (Super Admin or has configured permissions)
        $has_access = false;
        if ($user['role'] === 'super_admin') {
            $has_access = true;
        } else {
            $permStmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_name = ?");
            $permStmt->execute([$active_role]);
            if ($permStmt->fetchColumn() > 0) {
                $has_access = true;
            }
        }

        if ($has_access) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['active_role'] = $active_role;

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Access Denied: Your role does not have admin panel access.";
        }

    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - Sogasu</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary: #db2777;
            /* Pink */
            --primary-dark: #be185d;
            --surface: #ffffff;
            --background: #fdf2f8;
            --text-main: #4a044e;
            --text-muted: #86198f;
            --border: #fbcfe8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--surface);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 3rem;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .app-name {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .app-role {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--background);
            outline: none;
        }

        .btn-login {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .error-msg {
            color: red;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="logo-container">
        <div class="logo-icon"><i class="ri-admin-line"></i></div>
        <h1 class="app-name">Sogasu Admin</h1>
        <p class="app-role">Admin Dashboard Access</p>
    </div>

    <form class="login-form" method="POST">

        <div class="input-group">
            <i class="ri-mail-line input-icon"></i>
            <input type="email" name="email" class="form-input" placeholder="Email Address" required>
        </div>

        <div class="input-group">
            <i class="ri-lock-2-line input-icon"></i>
            <input type="password" name="password" class="form-input" placeholder="Password" required>
        </div>

        <button type="submit" class="btn-login">Login to Admin</button>

    </form>

    <?php if (!empty($error))
        echo "<p class='error-msg'>$error</p>"; ?>

</body>

</html>