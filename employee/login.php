<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mobile = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $errors = [];

    if (empty($mobile)) {
        $errors['mobile'] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = "Please enter a valid 10-digit phone number.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role = 'employee'");
        $stmt->execute([$mobile]);
        $user = $stmt->fetch();

        $error = '';

        if ($user && password_verify($password, $user['password'])) {

            $deviceToken = $_POST['device_token'] ?? '';
            if (empty($deviceToken)) {

                $error = "Device validation failed. Please refresh page.";

            } elseif (
                !empty($user['device_token']) &&
                $user['device_token'] !== $deviceToken
            ) {

                $error = "You are already logged in on another device.";

            } else {

                // Generate session token
                $sessionToken = bin2hex(random_bytes(32));

                // Store session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_token'] = $sessionToken;

                // Fetch employee preferred language
                $empStmt = $pdo->prepare("
                SELECT preferred_language, employee_type, job_role
                FROM employees 
                WHERE user_id = ?
            ");

                $empStmt->execute([$user['id']]);
                $emp = $empStmt->fetch();
                if (!$emp) {
                    $error = "Employee record not found";
                } else {

                    $_SESSION['employee_type'] = $emp['employee_type'] ?? 'inhouse';
                    $_SESSION['job_role'] = $emp['job_role'] ?? '';
                    $_SESSION['language'] = $emp['preferred_language'] ?? 'en';

                    // Update DB
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET
                            is_logged_in = 1,
                            session_token = ?,
                            device_token = ?    
                        WHERE id = ?
                    ");

                    $updateStmt->execute([
                        $sessionToken,
                        $deviceToken,
                        $user['id']
                    ]);

                    if (($emp['employee_type'] ?? '') === 'outsource') {
                        header("Location: outsourcing_dashboard.php");
                    } elseif (strtolower(trim($emp['job_role'] ?? '')) === 'supervisor') {
                        header("Location: supervisor-dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                }
            }

        } else {

            $error = "Invalid mobile or password";

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Employee Login - Sogasu</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary: #db2777;
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
            -webkit-tap-highlight-color: transparent;
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
            background: linear-gradient(135deg, var(--primary), #f472b6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3);
        }

        .app-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
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
            font-size: 1.2rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            background: var(--background);
            transition: all 0.2s;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.1);
        }

        .btn-login {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s;
            margin-top: 1rem;
            box-shadow: 0 4px 6px -1px rgba(219, 39, 119, 0.3);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .forgot-pass {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
            text-decoration: none;
        }

        .register-employee {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1rem;
            margin-left: auto;
            padding: 0.65rem 1rem;
            background: #fff0f6;
            border: 1px solid #f9a8d4;
            border-radius: 10px;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .register-employee:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
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

        .field-error {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.4rem;
            margin-left: 0.2rem;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="logo-container">
        <img src="../images/logo.svg"
            style="width: 100px; height: 100px; border-radius: 50%; box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3); margin-bottom: 1rem;">
        <h1 class="app-name">Sogasu Staff</h1>
        <p class="app-role">Employee Workspace</p>
    </div>

    <form class="login-form" method="POST">
        <input type="hidden" name="device_token" id="device_token">
        <div class="input-group">

            <div class="input-wrapper">
                <i class="ri-phone-line input-icon"></i>

                <input type="text" name="login" class="form-input" placeholder="Phone Number"
                    value="<?= htmlspecialchars($mobile ?? '') ?>" autocomplete="username">
            </div>

            <?php if (!empty($errors['mobile'])): ?>
                <div class="field-error"><?= $errors['mobile'] ?></div>
            <?php endif; ?>

        </div>
        <div class="input-group">

            <div class="input-wrapper">
                <i class="ri-lock-2-line input-icon"></i>

                <input type="password" id="password" name="password" class="form-input" placeholder="Password"
                    autocomplete="current-password">

                <i class="ri-eye-line password-toggle" id="togglePassword"></i>
            </div>

            <?php if (!empty($errors['password'])): ?>
                <div class="field-error"><?= $errors['password'] ?></div>
            <?php endif; ?>

        </div>

        <button type="submit" class="btn-login">Login to Workspace</button>
    </form>
    <a href="#" class="forgot-pass">Forgot Password?</a>
    <div style="display:flex;">
        <a href="../register.php" class="register-employee">
            <i class="ri-user-add-line"></i>
            Register Employee
        </a>
    </div>
    <script>
        function generateDeviceToken() {
            let token = localStorage.getItem("device_token");

            if (!token) {
                token =
                    'DEV-' +
                    navigator.platform +
                    '-' +
                    navigator.userAgent.length +
                    '-' +
                    screen.width +
                    'x' +
                    screen.height +
                    '-' +
                    Date.now();

                localStorage.setItem("device_token", token);
            }

            document.getElementById("device_token").value = token;
        }

        generateDeviceToken();
    </script>
    <?php if (!empty($error)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?= $error ?>',
                confirmButtonColor: '#db2777'
            });
        </script>
    <?php endif; ?>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        if (passwordInput && togglePassword) {
            togglePassword.addEventListener('click', function () {

                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';

                this.classList.toggle('ri-eye-line', !isHidden);
                this.classList.toggle('ri-eye-off-line', isHidden);

            });
        }
    </script>
</body>

</html>