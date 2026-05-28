<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../includes/db.php';

// Fetch the employee's ID
$stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found.");
}
$employee_id = $employee['id'];

// Fetch Assigned Assets
$stmt = $pdo->prepare("SELECT a.name, a.asset_code, a.condition_status, c.name as category_name, a.stock_quantity 
                       FROM assets a
                       LEFT JOIN asset_categories c ON a.category_id = c.id
                       WHERE a.assigned_employee_id = ?
                       ORDER BY a.name ASC");
$stmt->execute([$employee_id]);
$assets = $stmt->fetchAll();

$pageTitle = "My Assets - Sogasu Staff";
$headerTitle = "My Assets";
$activePage = "my-assets";
include 'includes/header.php';
?>

<div class="container">
    <div class="section-title">
        <span><i class="ri-macbook-line" style="color: var(--primary); margin-right: 8px;"></i>Assigned Assets</span>
        <span class="badge" style="background: var(--primary-light); color: var(--primary);"><?= count($assets) ?> Items</span>
    </div>

    <?php if (empty($assets)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1rem; border: 2px dashed var(--border); box-shadow: none;">
            <i class="ri-archive-line" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
            <h3 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 0.5rem;">No Assets Assigned</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">You currently don't have any physical assets or machines assigned to you.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 1rem;">
            <?php foreach ($assets as $asset): 
                // Determine condition badge style
                $condClass = 'completed'; // Green
                if ($asset['condition_status'] === 'Needs Repair') $condClass = 'progress'; // Orange
                if ($asset['condition_status'] === 'Broken') $condClass = 'pending'; // Red
            ?>
            <div class="card" style="margin-bottom: 0;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <h3 style="font-size: 1.1rem; color: var(--text-main); margin: 0 0 0.25rem 0;"><?= htmlspecialchars($asset['name']) ?></h3>
                        <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0; font-family: monospace; background: #f1f5f9; display: inline-block; padding: 2px 6px; border-radius: 4px;">
                            <?= htmlspecialchars($asset['asset_code']) ?>
                        </p>
                    </div>
                    <span class="badge <?= $condClass ?>"><?= htmlspecialchars($asset['condition_status']) ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 0.75rem; margin-top: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.85rem;">
                        <i class="ri-price-tag-3-line"></i>
                        <span><?= htmlspecialchars($asset['category_name'] ?? 'Uncategorized') ?></span>
                    </div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">
                        Qty: <?= htmlspecialchars($asset['stock_quantity']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/bottom_nav.php'; ?>
