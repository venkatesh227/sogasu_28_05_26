<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../employee/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sogasu Employee App</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/mobile.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body class="app-body">
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="top-row">
                <div class="flex items-center gap-2">
                    <div
                        style="width: 32px; height: 32px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="ri-user-smile-line"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 500;">Hi, Rajesh (Master)</span>
                </div>
                <div class="flex items-center gap-4">
                    <i class="ri-notification-3-line" style="font-size: 1.25rem;"></i>
                </div>
            </div>
            <h1>Your Workspace</h1>
            <p style="opacity: 0.8; font-size: 0.875rem;">You have <strong>4 active orders</strong> today.</p>
        </header>

        <!-- Main Content -->
        <main class="app-content">

            <!-- Quick Actions -->
            <div class="mobile-card">
                <div class="actions-grid" style="margin-bottom: 0;">
                    <div class="action-item">
                        <div class="action-icon"><i class="ri-camera-lens-line"></i></div>
                        <span class="action-label">Upload</span>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="ri-ruler-line"></i></div>
                        <span class="action-label">Measure</span>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="ri-file-list-3-line"></i></div>
                        <span class="action-label">Orders</span>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="ri-money-dollar-circle-line"></i></div>
                        <span class="action-label">Earnings</span>
                    </div>
                </div>
            </div>

            <!-- Active Orders List -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.125rem; font-weight: 600;">Today's Priorities</h3>
                <span class="text-primary" style="font-size: 0.875rem;">View All</span>
            </div>

            <!-- Order Card 1 -->
            <div class="mobile-card" style="padding: 1rem;">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex gap-3">
                        <div
                            style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                            <i class="ri-t-shirt-line" style="font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600;">Silk Blouse</h4>
                            <div class="text-muted" style="font-size: 0.75rem;">Order #2458 • Ananya S.</div>
                        </div>
                    </div>
                    <span
                        style="background: #e0e7ff; color: var(--primary); font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 600;">Cutting</span>
                </div>

                <div style="background: #f8fafc; padding: 0.75rem; border-radius: 8px; margin-top: 0.75rem;">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-muted" style="font-size: 0.75rem;">Deadline</span>
                        <span style="font-size: 0.875rem; font-weight: 500;">Today, 6:00 PM</span>
                    </div>
                    <div class="w-full bg-body"
                        style="height: 4px; border-radius: 2px; overflow: hidden; margin-top: 0.5rem;">
                        <div style="width: 40%; background: var(--warning); height: 100%;"></div>
                    </div>
                </div>

                <div class="flex gap-2 mt-3">
                    <button class="btn w-full"
                        style="background: #f1f5f9; color: var(--text-main); font-size: 0.875rem; padding: 0.5rem;">View
                        Details</button>
                    <button class="btn w-full btn-primary" style="font-size: 0.875rem; padding: 0.5rem;">Update
                        Status</button>
                </div>
            </div>

            <!-- Order Card 2 -->
            <div class="mobile-card" style="padding: 1rem;">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex gap-3">
                        <div
                            style="width: 48px; height: 48px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--warning);">
                            <i class="ri-scissors-2-line" style="font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600;">Lehenga Stitching</h4>
                            <div class="text-muted" style="font-size: 0.75rem;">Order #2455 • Priya K.</div>
                        </div>
                    </div>
                    <span
                        style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 600;">Stitching</span>
                </div>

                <div style="background: #f8fafc; padding: 0.75rem; border-radius: 8px; margin-top: 0.75rem;">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-muted" style="font-size: 0.75rem;">Deadline</span>
                        <span style="font-size: 0.875rem; font-weight: 500;">Tomorrow</span>
                    </div>
                    <div class="w-full bg-body"
                        style="height: 4px; border-radius: 2px; overflow: hidden; margin-top: 0.5rem;">
                        <div style="width: 75%; background: var(--primary); height: 100%;"></div>
                    </div>
                </div>

                <div class="flex gap-2 mt-3">
                    <button class="btn w-full"
                        style="background: #f1f5f9; color: var(--text-main); font-size: 0.875rem; padding: 0.5rem;">View
                        Details</button>
                    <button class="btn w-full btn-primary" style="font-size: 0.875rem; padding: 0.5rem;">Update
                        Status</button>
                </div>
            </div>

        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <button class="nav-btn active">
                <i class="ri-home-4-fill"></i>
                Home
            </button>
            <button class="nav-btn">
                <i class="ri-file-list-3-line"></i>
                Orders
            </button>
            <button class="nav-btn">
                <i class="ri-notification-3-line"></i>
                Alerts
            </button>
            <button class="nav-btn">
                <i class="ri-user-3-line"></i>
                Profile
            </button>
        </nav>
    </div>
</body>

</html>
