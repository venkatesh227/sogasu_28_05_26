<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../includes/db.php';
if (
    isset($_POST['mark_holiday_read']) &&
    $_POST['mark_holiday_read'] == '1'
) {

    $stmt = $pdo->prepare("

        UPDATE appointment_notifications
        SET status = 'read'
        WHERE user_id = ?
        AND message LIKE '%holiday%'
        AND status = 'pending'

    ");

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    exit;
}
$notificationStmt = $pdo->prepare("

    SELECT *
    FROM appointment_notifications
    WHERE user_id = ?
    AND status = 'pending'
    ORDER BY id DESC

");

$notificationStmt->execute([
    $_SESSION['user_id']
]);

$conflictNotifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
$showHolidayPopup = false;

foreach ($conflictNotifications as $notification) {

    if (
        $notification['status'] === 'pending' &&
        !empty($notification['message']) &&
        stripos($notification['message'], 'holiday') !== false
    ) {

        $showHolidayPopup = true;
        break;
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Sogasu'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary: #db2777;
            /* Pink-600 */
            --primary-light: #fce7f3;
            /* Pink-100 */
            --surface: #ffffff;
            --background: #fdf2f8;
            /* Pink-50 */
            --text-main: #4a044e;
            /* Fuchsia-950 */
            --text-muted: #86198f;
            /* Fuchsia-700 */
            --border: #fbcfe8;
            /* Pink-200 */
            --success: #059669;
            --warning: #d97706;
            --danger: #e11d48;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px;
            /* Space for bottom nav */
        }

        /* Top Header */
        .app-header {
            background: var(--surface);
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }

        .header-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 1px solid var(--surface);
        }

        /* Common Components */
        .container {
            padding: 1.25rem;
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge.pending {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fda4af;
        }

        .badge.progress {
            background: #fdf2f8;
            color: #db2777;
            border: 1px solid #f9a8d4;
        }

        .badge.completed {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(219, 39, 119, 0.3);
            text-decoration: none;
        }

        .notification-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 94%;
            max-width: 520px;
            max-height: 80vh;
            overflow-y: auto;

            background: #fff;
            border-radius: 16px;
            padding: 20px;
            z-index: 9999;

            display: none;

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            animation: popupFade 0.3s ease;
        }

        .notification-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9998;
            display: none;
        }

        @keyframes popupFade {
            from {
                opacity: 0;
                transform: translate(-50%, -45%);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .notification-header {
            padding: 14px 16px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        .notification-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .notification-text {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .accept-btn {
            background: #db2777;
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
        }

        .reject-btn {
            background: #f3f4f6;
            color: #374151;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
        }

        .empty-notification {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .close-btn {
            border: none;
            background: none;
            font-size: 20px;
            cursor: pointer;
            color: #6b7280;
        }

        <?php echo isset($extraHead) ? $extraHead : ''; ?>
    </style>
</head>

<body>

    <header class="app-header">
        <div class="header-title">
            <img src="../images/logo.svg" alt="Sogasu" style="height: 32px; border-radius: 50%;">
            <?php echo isset($headerTitle) ? $headerTitle : 'Sogasu'; ?>
        </div>
        <div class="header-actions">
            <a href="manage-addresses.php" class="icon-btn" title="My Addresses">
                <i class="ri-map-pin-2-fill"></i>
            </a>

            <button class="icon-btn">
                <i class="ri-shopping-bag-3-line"></i>
            </button>
            <button class="icon-btn notification-btn" onclick="toggleNotifications()">

                <i class="ri-notification-3-line"></i>
                

            </button>
            <button class="icon-btn">
                <img src="https://ui-avatars.com/api/?name=Rashmi+K&background=fce7f3&color=db2777"
                    style="width: 28px; height: 28px; border-radius: 50%;">
            </button>
        </div>
        <div class="notification-overlay" id="notificationOverlay"></div>

        <div class="notification-modal" id="notificationDropdown">

            <div class="notification-header">
                Notifications
            </div>

            <?php if (!empty($conflictNotifications)): ?>

                <?php foreach ($conflictNotifications as $notification): ?>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-notification">
                    No notifications
                </div>

            <?php endif; ?>

        </div>
    </header>
    <script>

        function toggleNotifications() {

            const modal = document.getElementById('notificationDropdown');
            const overlay = document.getElementById('notificationOverlay');

            modal.style.display = 'block';
            overlay.style.display = 'block';
        }

        function closeNotifications() {

            const modal = document.getElementById('notificationDropdown');
            const overlay = document.getElementById('notificationOverlay');

            modal.style.display = 'none';
            overlay.style.display = 'none';
        }
        <?php if ($showHolidayPopup): ?>

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'mark_holiday_read=1'
            });

            Swal.fire({
                icon: 'warning',
                title: 'Appointment Cancelled',
                text: 'Your appointment was cancelled due to a holiday. Please book another slot.',
                confirmButtonText: 'OK'
            });

        <?php endif; ?>

        document.getElementById('notificationOverlay')
            .addEventListener('click', closeNotifications);
        window.addEventListener('load', function () {

        });

    </script>
    <?php

    $hasHolidayCancellation = false;

    foreach ($conflictNotifications as $notification) {

        if (
            $notification['status'] === 'pending' &&
            !empty($notification['message']) &&
            stripos($notification['message'], 'holiday') !== false
        ) {

            $hasHolidayCancellation = true;
            break;
        }
    }

    ?>

    <?php if (
        $hasHolidayCancellation &&
        basename($_SERVER['PHP_SELF']) === 'dashboard.php'
    ): ?>

        <script>

            document.addEventListener('DOMContentLoaded', function () {

                Swal.fire({
                    icon: 'warning',
                    title: 'Appointment Update',
                    text:
                        'Your booked appointment slot has been cancelled due to a holiday. Please rebook your appointment.',
                    confirmButtonColor: '#ef4444'
                }).then(() => {

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'mark_holiday_read=1'
                    });

                });

            });

        </script>

    <?php endif; ?>
    <?php if (!empty($_SESSION['success_message'])): ?>

        <script>

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= $_SESSION['success_message']; ?>',
                confirmButtonText: 'OK'
            });

        </script>

        <?php unset($_SESSION['success_message']); ?>

    <?php endif; ?>