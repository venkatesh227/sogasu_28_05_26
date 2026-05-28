<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Fetch upcoming holidays
$stmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date >= CURRENT_DATE() ORDER BY holiday_date ASC LIMIT 10");
$stmt->execute();
$holidays = $stmt->fetchAll();

$pageTitle = "Holidays - Sogasu";
$headerTitle = "Holiday List";
$activePage = "holidays";
include 'includes/header.php';
?>

<div class="container">
    <div class="section-title">Upcoming Holidays</div>
    
    <?php if (empty($holidays)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; color: #94a3b8;">
            <i class="ri-calendar-line" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
            <p>No upcoming holidays scheduled.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($holidays as $h): 
                $h_date = strtotime($h['holiday_date']);
                $is_this_month = (date('m', $h_date) === date('m'));
            ?>
                <div class="card" style="padding: 1rem; border-left: 4px solid <?= $h['color'] ?>; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="text-align: center; min-width: 45px;">
                                <div style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;"><?= date('M', $h_date) ?></div>
                                <div style="font-size: 1.2rem; font-weight: 800; color: #1e293b;"><?= date('d', $h_date) ?></div>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($h['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;"><?= date('l', $h_date) ?></div>
                            </div>
                        </div>
                        <div style="background: <?= $h['color'] ?>20; color: <?= $h['color'] ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">
                            <?= $h['type'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 2rem; padding: 1.5rem; background: #fef2f2; border-radius: 16px; border: 1px solid #fee2e2;">
        <div style="display: flex; align-items: center; gap: 0.75rem; color: #dc2626; margin-bottom: 0.5rem;">
            <i class="ri-information-line" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 700; font-size: 0.9rem;">Company Policy</span>
        </div>
        <p style="font-size: 0.8rem; color: #991b1b; line-height: 1.5;">
            Holidays are subject to company workload. Please check with your supervisor for mandatory duty requirements on optional holidays.
        </p>
    </div>
</div>

<?php include 'includes/bottom-nav.php'; ?>
