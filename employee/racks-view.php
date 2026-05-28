<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch all racks with order info
$stmt = $pdo->query("
    SELECT r.*, o.order_code, o.id as order_id, sc.name as garment
    FROM racks r
    LEFT JOIN orders o ON o.rack_id = r.id AND o.order_status != 'delivered'
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    ORDER BY r.rack_name ASC
");
$racks = $stmt->fetchAll();

$pageTitle = "Rack Management - Sogasu";
$headerTitle = "Racks & Storage";
include 'includes/header.php';
?>

<div class="container" style="padding-bottom: 80px;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <a href="dashboard.php" style="color: #64748b; text-decoration: none; font-size: 1.2rem;"><i class="ri-arrow-left-s-line"></i></a>
        <div style="text-align: right;">
            <div style="font-size: 0.75rem; color: #64748b;">Total Racks</div>
            <div style="font-weight: 700; color: var(--text-main);"><?= count($racks) ?></div>
        </div>
    </div>

    <!-- Search / Filter -->
    <div style="position: relative; margin-bottom: 1.5rem;">
        <i class="ri-search-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
        <input type="text" id="rackSearch" placeholder="Search rack name..." style="width: 100%; padding: 0.85rem 1rem 0.85rem 3rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; font-size: 0.9rem;">
    </div>

    <!-- Rack Grid -->
    <div id="rackGrid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <?php foreach($racks as $r): ?>
            <div class="rack-card card" data-name="<?= strtolower($r['rack_name']) ?>" style="margin-bottom: 0; padding: 1rem; border-top: 4px solid <?= $r['order_code'] ? '#f59e0b' : '#10b981' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div style="width: 40px; height: 40px; background: <?= $r['order_code'] ? '#fff7ed' : '#f0fdf4' ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="ri-archive-line" style="font-size: 1.25rem; color: <?= $r['order_code'] ? '#f59e0b' : '#10b981' ?>;"></i>
                    </div>
                    <span style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: <?= $r['order_code'] ? '#fed7aa' : '#bbf7d0' ?>; color: <?= $r['order_code'] ? '#9a3412' : '#166534' ?>; font-weight: 700;">
                        <?= $r['order_code'] ? 'OCCUPIED' : 'VACANT' ?>
                    </span>
                </div>
                
                <div style="font-weight: 700; font-size: 1rem; color: #1e293b;"><?= htmlspecialchars($r['rack_name']) ?></div>
                
                <?php if($r['order_code']): ?>
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0;">
                        <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Stored Item:</div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: #4338ca;">#<?= $r['order_code'] ?></div>
                        <div style="font-size: 0.75rem; color: #475569;"><?= $r['garment'] ?></div>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 0.75rem; color: #94a3b8; font-size: 0.75rem; font-style: italic;">
                        Available for storage
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
    document.getElementById('rackSearch').addEventListener('input', function(e) {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.rack-card').forEach(card => {
            const name = card.getAttribute('data-name');
            card.style.display = name.includes(q) ? 'block' : 'none';
        });
    });
</script>

<?php include 'includes/bottom-nav.php'; ?>
