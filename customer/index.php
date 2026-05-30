<?php
session_start();
require '../includes/db.php';
if (
    !isset($_POST['send_otp']) &&
    !isset($_POST['verify_otp']) &&
    !(isset($_GET['action']) && $_GET['action'] == 'resend_otp') &&
    !(isset($_GET['action']) && $_GET['action'] == 'change_mobile') &&
    !isset($_GET['otp_sent'])
) {
    unset($_SESSION['otp']);
}

$msg = "";

// STEP 1: SEND OTP
if (isset($_POST['send_otp'])) {

    $mobile = $_POST['mobile'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role = 'customer'");
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();

    if (!$user) {
        $msg = "Mobile not registered";
    } else {

        $_SESSION['otp'] = rand(100000, 999999);
        $_SESSION['mobile'] = $mobile;

        // (same as reference → no login here)
    }
}


// STEP 2: RESEND OTP
if (isset($_GET['action']) && $_GET['action'] == 'resend_otp') {

    $_SESSION['otp'] = rand(100000, 999999);

    header("Location: index.php?otp_sent=1");
    exit();
}


// STEP 3: CHANGE MOBILE (REFERENCE HAS THIS)
if (isset($_GET['action']) && $_GET['action'] == 'change_mobile') {

    unset($_SESSION['otp']);

    unset($_SESSION['show_otp_screen']);
    unset($_SESSION['mobile']);

    header("Location: index.php");
    exit();
}


// STEP 4: VERIFY OTP
if (isset($_POST['verify_otp'])) {

    if ($_SESSION['otp'] == $_POST['otp']) {

        // 🔥 IMPORTANT: FETCH USER AGAIN (REFERENCE STYLE)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role = 'customer'");
        $stmt->execute([$_SESSION['mobile']]);
        $user = $stmt->fetch();

        if ($user) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'customer';
           
            $_SESSION['customer_name'] = $user['username'];

            unset($_SESSION['otp']);
            unset($_SESSION['mobile']);

            header("Location: dashboard.php");
            exit();

        } else {
            $msg = "User not found";
        }

    } else {
        $msg = "Wrong OTP";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Login - Sogasu</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary: #db2777;
            /* Pink-600 */
            --primary-dark: #be185d;
            --surface: #ffffff;
            --background: #fdf2f8;
            /* Pink-50 */
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
            background-image: radial-gradient(circle at top right, #fce7f3 0%, transparent 40%),
                radial-gradient(circle at bottom left, #fce7f3 0%, transparent 40%);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .app-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
        }

        .app-tagline {
            color: #db2777;
            font-size: 1rem;
            line-height: 1.5;
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
            color: #db2777;
            font-size: 1.3rem;
            z-index: 2;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.2s;
            outline: none;
            backdrop-filter: blur(4px);
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

        .social-login {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }

        .social-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .divider {
            text-align: center;
            margin: 2rem 0 1rem;
            position: relative;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: var(--border);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        /* OTP action buttons */
        .otp-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .otp-link {
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #db2777;
            padding: 6px 10px;
            border-radius: 6px;
            transition: 0.2s;
        }

        .otp-link:hover {
            background: rgba(219, 39, 119, 0.1);
        }

        .otp-link.secondary {
            color: #6b7280;
        }
    </style>
</head>

<body>

    <div class="logo-container">
        <img src="../images/logo.svg"
            style="width: 100px; height: 100px; border-radius: 50%; box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3); margin-bottom: 1rem;">
        <h1 class="app-name">Sogasu</h1>
        <p class="app-tagline">Custom tailoring experience<br>redefined for you.</p>
    </div>

    <form class="login-form" method="POST">

        <?php if (!isset($_SESSION['otp'])) { ?>

            <div class="input-group">
                <i class="ri-phone-line input-icon"></i>
                <input type="tel" name="mobile" class="form-input" placeholder="Mobile Number" required>
            </div>

            <button type="submit" name="send_otp" class="btn-login">Get OTP</button>

        <?php } else { ?>

            <div class="input-group">
                <i class="ri-key-line input-icon"></i>
                <input type="text" name="otp" maxlength="6" class="form-input" placeholder="Enter OTP"
                    value="<?php echo isset($_SESSION['otp']) ? $_SESSION['otp'] : ''; ?>" required>
            </div>
            <button type="submit" name="verify_otp" class="btn-login">Verify OTP</button>
            <div class="otp-actions">
                <a href="?action=resend_otp" class="otp-link">Resend OTP</a>
                <a href="?action=change_mobile" class="otp-link secondary">Change Mobile</a>
            </div>
        <?php } ?>
        <p style="color:red; text-align:center;"><?php echo $msg; ?></p>
    </form>
    <div class="divider">Or continue with</div>
    <div class="social-login">
        <button class="social-btn"><i class="ri-google-fill"></i></button>
        <button class="social-btn"><i class="ri-apple-fill"></i></button>
        <button class="social-btn"><i class="ri-facebook-fill"></i></button>
    </div>
</body>

</html>