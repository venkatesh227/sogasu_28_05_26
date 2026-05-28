<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Customer ID is required");
}

$stmt = $pdo->prepare("
    SELECT c.*, u.status,
    (SELECT COUNT(*) FROM customer_family_members WHERE customer_id = c.id AND is_deleted = 0) as family_count 
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = ? AND c.is_deleted = 0
");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Customer not found");
}

// Fetch Orders
$orderStmt = $pdo->prepare("
    SELECT o.id, o.order_code, o.due_date, o.order_status, sc.name as garment,
    (SELECT image_path FROM order_images WHERE order_id = o.id AND image_type = 'fabric' LIMIT 1) as fabric_img
    FROM orders o
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    WHERE o.customer_id = ? AND o.is_deleted = 0
    ORDER BY o.id DESC
");
$orderStmt->execute([$id]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "View Customer - Sogasu";
$activePage = "customers";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Customer Profile</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">UID: #<?= $customer['id'] ?></p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-primary" onclick="window.location.href='add-customer.php?id=<?= $customer['id'] ?>'" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white;">
                    <i class="ri-pencil-line"></i> Edit Customer
                </button>
                <button class="btn btn-light" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                    <i class="ri-arrow-left-line"></i> Back to List
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
            
            <!-- Left Column: Profile Card -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="table-container" style="margin-top: 0; text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: #eef2ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; margin: 0 auto 1rem auto;">
                        <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">
                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                    </h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">
                        <i class="ri-map-pin-line"></i> <?= htmlspecialchars($customer['area'] . ', ' . $customer['city']) ?>
                    </p>
                    
                    <?php $statusColor = ($customer['status'] == 1) ? '#10b981' : '#ef4444'; ?>
                    <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                        <?= ($customer['status'] == 1) ? 'Active' : 'Inactive' ?>
                    </span>
                    
                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; text-align: left; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 32px; height: 32px; background: #f8fafc; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #64748b;"><i class="ri-phone-line"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Primary Phone</div>
                                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($customer['phone']) ?></div>
                            </div>
                        </div>
                        <?php if(!empty($customer['secondary_phone'])): ?>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 32px; height: 32px; background: #f8fafc; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #64748b;"><i class="ri-phone-line"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Secondary Phone</div>
                                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($customer['secondary_phone']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 32px; height: 32px; background: #f8fafc; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #64748b;"><i class="ri-mail-line"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Email Address</div>
                                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($customer['email'] ?: 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-container" style="margin-top: 0; padding: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">Preferences</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <div class="meta-label">Preferred Branch</div>
                            <div class="meta-value"><?= htmlspecialchars($customer['branch'] ?: 'N/A') ?></div>
                        </div>
                        <div>
                            <div class="meta-label">Source</div>
                            <div class="meta-value"><?= htmlspecialchars($customer['source'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="meta-label">Internal Notes</div>
                    <div class="meta-value" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-top: 0.5rem; white-space: pre-wrap; font-size: 0.85rem; line-height: 1.5; border: 1px solid #f1f5f9;"><?= htmlspecialchars($customer['notes'] ?: 'No notes available.') ?></div>
                </div>
            </div>

            <!-- Right Column: Address, Family, Orders -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="table-container" style="margin-top: 0; padding: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">Address Details</h3>
                        <div class="meta-label">Street Address</div>
                        <div class="meta-value" style="margin-bottom: 1rem;"><?= htmlspecialchars($customer['address'] ?: 'N/A') ?></div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <div class="meta-label">Area</div>
                                <div class="meta-value"><?= htmlspecialchars($customer['area'] ?: 'N/A') ?></div>
                            </div>
                            <div>
                                <div class="meta-label">City</div>
                                <div class="meta-value"><?= htmlspecialchars($customer['city'] ?: 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-container" style="margin-top: 0; padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; background: #faf5ff; border-color: #f3e8ff;">
                        <div style="width: 48px; height: 48px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #a855f7; font-size: 1.5rem; margin-bottom: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                            <i class="ri-group-line"></i>
                        </div>
                        <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">Family Members</h3>
                        <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem;">Manage measurements for <?= $customer['family_count'] ?> registered family members.</p>
                        <a href="customer-family.php?id=<?= $customer['id'] ?>" class="btn" style="background: white; border: 1px solid #e9d5ff; color: #9333ea; font-weight: 600;">View Family</a>
                    </div>
                </div>

                <div class="table-container" style="margin-top: 0;">
                    <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent Orders</h3>
                        <a href="add-order.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm" style="background: #eef2ff; color: #4f46e5; border: none; font-weight: 600;"><i class="ri-add-line"></i> New Order</a>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <div style="width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 2rem; margin: 0 auto 1rem auto;">
                                <i class="ri-shopping-bag-line"></i>
                            </div>
                            <h4 style="font-size: 1rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">No Orders Yet</h4>
                            <p style="color: #94a3b8; font-size: 0.85rem;">This customer hasn't placed any orders.</p>
                        </div>
                    <?php else: ?>
                        <table class="table" style="width: 100%; margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 1rem;">Order No</th>
                                    <th style="padding: 1rem;">Garment</th>
                                    <th style="padding: 1rem;">Status</th>
                                    <th style="padding: 1rem;">Due Date</th>
                                    <th style="padding: 1rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): 
                                    $statusColor = match($o['order_status']) {
                                        'pending' => '#f59e0b',
                                        'stitching' => '#6366f1',
                                        'ready' => '#0891b2',
                                        'delivered' => '#10b981',
                                        'cancelled' => '#ef4444',
                                        default => '#64748b'
                                    };
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 1rem; font-weight: 600; color: #4f46e5;">#<?= htmlspecialchars($o['order_code']) ?></td>
                                    <td style="padding: 1rem; color: #475569;"><?= htmlspecialchars($o['garment'] ?? 'General') ?></td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                                            <?= ucfirst($o['order_status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; color: #64748b; font-size: 0.85rem;"><?= date('d M Y', strtotime($o['due_date'])) ?></td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <a href="view-order.php?id=<?= $o['id'] ?>" style="color: #64748b; text-decoration: none;"><i class="ri-arrow-right-line"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</main>

<style>
    .meta-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.25rem; }
    .meta-value { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
</style>

<?php include 'includes/footer.php'; ?>
