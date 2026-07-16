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

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $errors = [];

    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    if (empty($errors)) {

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
            width: 100%;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.15rem;
            transition: color .2s ease;
        }

        .password-toggle:hover {
            color: var(--primary);
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

        .field-error {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.4rem;
            margin-left: 0.2rem;
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

            <div class="input-wrapper">
                <i class="ri-mail-line input-icon"></i>

                <input type="text" name="email" class="form-input" placeholder="Email Address"
                    value="<?= htmlspecialchars($email ?? '') ?>" autocomplete="username">
            </div>

            <?php if (!empty($errors['email'])): ?>
                <div class="field-error"><?= $errors['email'] ?></div>
            <?php endif; ?>

        </div>

        <div class="input-group">

            <div class="input-wrapper">
                <i class="ri-lock-2-line input-icon"></i>

                <input type="password" id="password" name="password" class="form-input" placeholder="Password">

                <i class="ri-eye-line password-toggle" id="togglePassword"></i>
            </div>

            <?php if (!empty($errors['password'])): ?>
                <div class="field-error"><?= $errors['password'] ?></div>
            <?php endif; ?>

        </div>

        <button type="submit" class="btn-login">Login to Admin</button>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

    </form>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function () {

            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';

            this.classList.toggle('ri-eye-line', !isHidden);
            this.classList.toggle('ri-eye-off-line', isHidden);

        });
    </script>

</body>

</html>