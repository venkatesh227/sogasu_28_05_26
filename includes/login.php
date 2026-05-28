<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['email'] ?? ''); // keeping the POST key as 'email' for compatibility
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        exit("Email/Mobile and Password required");
    }

    // Try finding by email for super admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'super_admin'");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    // If not found, try by mobile for everyone else
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role != 'super_admin'");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
    }

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // ✅ Correct redirects
        if ($user['role'] === 'super_admin') {
            header("Location: ../admin/dashboard.php");
        } elseif ($user['role'] === 'employee') {
            // Check if supervisor
            $stmt_job = $pdo->prepare("
                SELECT j.role_name 
                FROM employees e 
                LEFT JOIN job_roles j ON (e.job_role = j.id OR e.job_role = j.role_name)
                WHERE e.user_id = ?
            ");
            $stmt_job->execute([$user['id']]);
            $job_role = $stmt_job->fetchColumn();

            if ($job_role === 'Supervisor') {
                header("Location: ../employee/dashboard.php");
            } else {
                header("Location: ../app/home.php");
            }
        } elseif ($user['role'] === 'customer') {
            header("Location: ../app/home.php"); // if same UI
        } elseif ($user['role'] === 'accountant') {
            header("Location: ../accounts/dashboard.php");
        } else {
            session_destroy();
            exit("Invalid role");
        }

        exit();

    } else {
        echo "Invalid credentials";
    }
}
?>