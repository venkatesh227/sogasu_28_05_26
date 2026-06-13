<?php
session_start();
require_once '../includes/db.php';

$pageTitle = "Notifications - Sogasu";
$headerTitle = "Notifications";
$activePage = "notifications";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* =========================
   GET OUTSOURCE EMPLOYEE ID
========================= */
$stmt = $pdo->prepare("
    SELECT id 
    FROM employees 
    WHERE user_id = ?
    AND employee_type = 'outsource'
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$employee_id = $stmt->fetchColumn();

if (!$employee_id) {
    die("Outsource employee not found.");
}

/* =========================
   FETCH NOTIFICATIONS
========================= */
$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE employee_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$employee_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   MARK AS READ
========================= */
$pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE employee_id = ?
    AND is_read = 0
")->execute([$employee_id]);

include 'includes/header.php';
?>

<div class="container" style="padding-bottom: 100px;">

    <?php if (empty($notifications)): ?>
        <div class="card" style="text-align:center; padding:3rem 1.5rem; border-style:dashed; border-radius:20px;">
            <div style="width:60px; height:60px; background:#fdf2f8; color:#db2777; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                <i class="ri-notification-off-line" style="font-size:2rem;"></i>
            </div>

            <div style="font-weight:700; color:#1e293b; margin-bottom:0.25rem;">
                No Notifications
            </div>

            <div style="font-size:0.85rem; color:#64748b;">
                You're all caught up!
            </div>
        </div>

    <?php else: ?>

        <div style="display:flex; flex-direction:column; gap:0.75rem;">

            <?php foreach ($notifications as $n): ?>

                <?php
                $icon = "ri-information-line";
                $iconColor = "#db2777";

                $title = strtolower($n['title']);

                if (strpos($title, 'accepted') !== false) {
                    $icon = "ri-checkbox-circle-line";
                    $iconColor = "#22c55e";
                } elseif (strpos($title, 'rejected') !== false) {
                    $icon = "ri-close-circle-line";
                    $iconColor = "#ef4444";
                } elseif (strpos($title, 'payment') !== false) {
                    $icon = "ri-wallet-3-line";
                    $iconColor = "#f59e0b";
                } elseif (strpos($title, 'assigned') !== false) {
                    $icon = "ri-file-list-3-line";
                    $iconColor = "#3b82f6";
                }
                ?>

                <div class="card"
                     style="padding:1.25rem; display:flex; gap:1rem; border-radius:16px;
                     <?= !$n['is_read'] ? 'background:#fdf2f8; border-color:#fbcfe8;' : '' ?>">

                    <div style="
                        width:40px;
                        height:40px;
                        background:white;
                        color:<?= $iconColor ?>;
                        border-radius:50%;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:1.25rem;
                        flex-shrink:0;
                        box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <i class="<?= $icon ?>"></i>
                    </div>

                    <div style="flex:1;">
                        <div style="
                            font-weight:700;
                            color:#1e293b;
                            margin-bottom:0.25rem;
                            font-size:0.95rem;">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>

                        <div style="
                            font-size:0.85rem;
                            color:#475569;
                            line-height:1.4;">
                            <?= htmlspecialchars($n['message']) ?>
                        </div>

                        <div style="
                            font-size:0.7rem;
                            font-weight:600;
                            color:#94a3b8;
                            margin-top:0.5rem;">
                            <i class="ri-time-line"></i>
                            <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>
</div>

<?php include 'includes/outsource-bottom-nav.php'; ?>