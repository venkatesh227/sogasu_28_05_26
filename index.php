<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sogasu - Boutique Management System</title>
    <link rel="stylesheet" href="css/global.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a237e 0%, #0d1245 100%);
            padding: 1rem;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .brand-logo {
            width: 64px;
            height: 64px;
            background: var(--primary);
            color: white;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .role-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            text-align: left;
            transition: all 0.2s;
            cursor: pointer;
        }
        .role-btn:hover {
            border-color: var(--primary);
            background: #f0f4ff;
            transform: translateX(5px);
        }
        .role-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1 style="margin-bottom: 0.5rem; color: var(--primary);">SOGASU</h1>
        <p class="text-muted" style="margin-bottom: 2rem;">Boutique Management System</p>

        <h3 style="text-align: left; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">Select Role to Login</h3>

        <div class="role-btn" onclick="window.location.href='admin/login.php'">
            <div class="role-icon"><i class="ri-admin-line"></i></div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Super Admin</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Access full dashboard</div>
            </div>
        </div>

        <div class="role-btn" onclick="window.location.href='employee/login.php'">
            <div class="role-icon"><i class="ri-user-star-line"></i></div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Employee</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">View orders & upload work</div>
            </div>
        </div>

        <div class="role-btn" onclick="window.location.href='customer/index.php'">
            <div class="role-icon"><i class="ri-shopping-bag-3-line"></i></div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Customer</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Track orders & status</div>
            </div>
        </div>

    </div>

</body>
</html>
