<?php
session_start();
require '../includes/db.php';
// Fetch logged in customer details
$customerId = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE user_id = ?
");

$stmt->execute([$customerId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$customerName = trim(
    ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')
);

$customerMobile = $customer['phone'] ?? '';

$initials = '';

if (!empty($customer['first_name'])) {
    $initials .= strtoupper(substr($customer['first_name'], 0, 1));
}

if (!empty($customer['last_name'])) {
    $initials .= strtoupper(substr($customer['last_name'], 0, 1));
}

// Create profiles table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS customer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    section_type ENUM('addresses', 'measurements', 'support', 'settings') NOT NULL,
    data LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_section (user_id, section_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pageTitle = "My Profile - Sogasu";
$headerTitle = "Profile";
$activePage = "profile";
include 'includes/header.php';
?>

<div class="container">

    <!-- Profile Header -->
    <div class="card"
        style="text-align: center; padding: 2rem 1rem; border: none; background: linear-gradient(135deg, var(--background), #fff);">
        <div style="position: relative; display: inline-block; margin-bottom: 1rem;">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($initials); ?>&background=fce7f3&color=db2777&size=128"
                style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <button
                style="position: absolute; bottom: 0; right: 0; background: var(--primary); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                <i class="ri-camera-line" style="font-size: 1rem;"></i>
            </button>
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
            <?php echo htmlspecialchars($customerName); ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            +91 <?php echo htmlspecialchars($customerMobile); ?>
        </p>
    </div>

    <!-- Menu List -->
    <div class="card" style="padding: 0;">
        <a href="addresses.php"
            style="padding: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit;">
            <div
                style="width: 40px; height: 40px; background: var(--background); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <i class="ri-map-pin-line" style="font-size: 1.25rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 1rem;">My Addresses</div>
            </div>
            <i class="ri-arrow-right-s-line" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </a>

        <a href="measurements.php"
            style="padding: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit;">
            <div
                style="width: 40px; height: 40px; background: var(--background); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <i class="ri-ruler-line" style="font-size: 1.25rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 1rem;">Saved Measurements</div>
            </div>
            <i class="ri-arrow-right-s-line" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </a>

        <a href="support.php"
            style="padding: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit;">
            <div
                style="width: 40px; height: 40px; background: var(--background); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <i class="ri-customer-service-2-line" style="font-size: 1.25rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 1rem;">Help & Support</div>
            </div>
            <i class="ri-arrow-right-s-line" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </a>

        <a href="settings.php"
            style="padding: 1rem; display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit;">
            <div
                style="width: 40px; height: 40px; background: var(--background); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <i class="ri-settings-line" style="font-size: 1.25rem;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 1rem;">Settings</div>
            </div>
            <i class="ri-arrow-right-s-line" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </a>
    </div>

    <!-- Logout -->
    <button onclick="window.location.href='../includes/logout.php'"
        style="width: 100%; background: #fff1f2; color: var(--danger); border: none; padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
        <i class="ri-logout-box-line"></i> Logout
    </button>

    <div style="text-align: center; margin-top: 2rem; color: var(--text-muted); font-size: 0.8rem;">
        App Version 2.1.0
    </div>

</div>

<<<<<<< Updated upstream
<?php include 'includes/bottom-nav.php'; ?>
=======
<?php include 'includes/bottom-nav.php'; ?>

>>>>>>> Stashed changes
