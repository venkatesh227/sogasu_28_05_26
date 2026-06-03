<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$language = $_SESSION['language'] ?? 'en';

// Include translations
require_once __DIR__ . '/includes/translations.php';
$t = $translations[$language] ?? $translations['en'];

// Fetch employee data
$stmt = $pdo->prepare("SELECT id, preferred_language FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

if (!$emp) {
    die("Employee record not found.");
}

$employee_id = $emp['id'];

if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = $emp['preferred_language'] ?? 'en';
    $language = $_SESSION['language'];
    $t = $translations[$language] ?? $translations['en'];
}

// 1. Fetch Payments (Salary, Advance Deductions, etc.)
$stmt = $pdo->prepare("SELECT id, payment_date as date, description, payment_type as type, amount, status, 'payment' as source FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC LIMIT 20");
$stmt->execute([$employee_id]);
$payments = $stmt->fetchAll();

// 2. Fetch Overtime
$stmt = $pdo->prepare("SELECT id, ot_date as date, description, 'OT' as type, amount, status, 'ot' as source FROM employee_overtime WHERE employee_id = ? ORDER BY ot_date DESC LIMIT 20");
$stmt->execute([$employee_id]);
$overtimes = $stmt->fetchAll();



// Merge and sort by date
$history = array_merge($payments, $overtimes);
usort($history, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// 3. Calculate Stats for Current Month
// 3. Calculate Total Net Earnings (All Time)
$totalEarned = 0;
$pendingAmount = 0;

$stmt = $pdo->prepare("
    SELECT payment_type, amount, status
    FROM employee_payments
    WHERE employee_id = ?
");
$stmt->execute([$employee_id]);
$allPayments = $stmt->fetchAll();

foreach ($allPayments as $row) {

    if ($row['status'] == 'Paid') {

        // Add Salary, OT, Bonus
        if (in_array($row['payment_type'], ['Salary', 'Overtime', 'Bonus', 'Bonus/Incentive'])) {
            $totalEarned += $row['amount'];
        }

        // Subtract Advance Given
        if ($row['payment_type'] == 'Advance') {
            $totalEarned -= $row['amount'];
        }
    }

    // Subtract Deducted items
    if ($row['status'] == 'Deducted') {
        $totalEarned -= $row['amount'];
    }
}

            

$pageTitle = $t['my_earnings'] . " - Sogasu Staff";
$headerTitle = $t['earnings'];
$activePage = "earnings";
include 'includes/header.php';
?>

<div class="container" style="padding-bottom: 100px;">
    
    <!-- Earnings Summary Card -->
    <div class="card" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; border: none; padding: 2rem 1.5rem; border-radius: 24px; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4);">
        <div style="position: absolute; right: -30px; top: -30px; font-size: 10rem; opacity: 0.05; transform: rotate(-15deg);">
            <i class="ri-bank-card-line"></i>
        </div>
        
        <div style="position: relative; z-index: 1;">
            <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.7; margin-bottom: 0.5rem;">Total Earnings</div>
            <div style="font-size: 2.75rem; font-weight: 800; line-height: 1; margin-bottom: 1.5rem;">₹<?= number_format($totalEarned, 0) ?></div>
            
            <div style="display: flex; gap: 0.75rem;">
                <div style="background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.3); padding: 0.5rem 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%;"></div>
                    <span style="font-size: 0.75rem; font-weight: 600;">Salary Paid</span>
                </div>
                <?php if ($pendingAmount > 0): ?>
                <div style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.5rem 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%;"></div>
                    <span style="font-size: 0.75rem; font-weight: 600;">₹<?= number_format($pendingAmount, 0) ?> Pending</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History List -->
    <div class="section-title" style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <span>Recent Activity</span>
        <i class="ri-filter-3-line" style="color: #64748b;"></i>
    </div>

    <?php if (empty($history)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; border-style: dashed;">
            <div style="width: 60px; height: 60px; background: #f1f5f9; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ri-history-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="color: #64748b; font-size: 0.9rem;">No payment history found.</div>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
            <?php foreach ($history as $index => $item): ?>
                <div style="padding: 1.25rem; border-bottom: <?= ($index === count($history)-1) ? 'none' : '1px solid #f1f5f9' ?>; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; 
                            background: <?= ($item['source'] === 'ot') ? '#f0f9ff' : ($item['type'] === 'Advance Deduction' ? '#fef2f2' : '#f0fdf4') ?>; 
                            color: <?= ($item['source'] === 'ot') ? '#0369a1' : ($item['type'] === 'Advance Deduction' ? '#b91c1c' : '#15803d') ?>;">
                            <i class="<?= ($item['source'] === 'ot') ? 'ri-time-line' : ($item['type'] === 'Advance Deduction' ? 'ri-hand-coin-line' : 'ri-wallet-3-line') ?>" style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 1rem; color: #1e293b;"><?= htmlspecialchars($item['description'] ?: ($item['source'] === 'ot' ? 'Overtime Pay' : 'Salary Payout')) ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;"><?= date('D, d M', strtotime($item['date'])) ?> • <?= $item['type'] ?></div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 800; font-size: 1.1rem; color: <?= ($item['type'] === 'Advance Deduction') ? '#ef4444' : '#22c55e' ?>;">
                            <?= ($item['type'] === 'Advance Deduction') ? '- ' : '+ ' ?>₹<?= number_format($item['amount'], 0) ?>
                        </div>
                        <div style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; 
                            color: <?= ($item['status'] === 'Paid' || $item['status'] === 'Approved') ? '#10b981' : '#f59e0b' ?>;">
                            <?= $item['status'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<style>
    .container {
        animation: fadeIn 0.4s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php include 'includes/bottom-nav.php'; ?>
