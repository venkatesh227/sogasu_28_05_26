<?php
session_start();
include '../includes/db.php';
$stmt = $pdo->query("
    SELECT 
        o.id,
        o.order_code,
        o.total_amount,
        o.due_date,
        o.order_status,

        c.first_name,
        c.last_name,

        u.mobile,

        sc.name AS garment,

        e.first_name AS emp_first,
        e.last_name AS emp_last,
        e.job_role

    FROM orders o

    LEFT JOIN customers c 
        ON o.customer_id = c.id AND c.is_deleted = 0

    LEFT JOIN users u 
        ON c.user_id = u.id AND u.status = 1

    LEFT JOIN sub_categories sc 
        ON o.sub_category_id = sc.id AND sc.is_deleted = 0

    LEFT JOIN employees e 
        ON o.assigned_employee_id = e.id AND e.is_deleted = 0

    WHERE o.is_deleted = 0

    ORDER BY o.id DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = "All Orders - Sogasu";
$activePage = "all-orders";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">All Orders</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Comprehensive list of all customer orders</p>
            </div>
            <a href="add-order.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
                <i class="ri-add-line"></i> New Order
            </a>
        </div>

        <!-- Orders Table (Standard Table Box) -->
        <div class="table-container">
            <table id="allOrdersTable" class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Garment</th>
                        <th>Amount</th>
                        <th>Assigned To</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $row): 
                        $statusColor = match(strtolower($row['order_status'])) {
                            'pending' => '#f59e0b',
                            'processing' => '#6366f1',
                            'completed' => '#10b981',
                            'ready' => '#0891b2',
                            default => '#64748b'
                        };
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 700; color: #4f46e5;">#<?= htmlspecialchars($row['order_code']) ?></td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['first_name'] . ' ' . ($row['last_name'] ?? '')) ?></div>
                            <div style="font-size: 0.75rem; color: #64748b;">+91 <?= htmlspecialchars($row['mobile']) ?></div>
                        </td>
                        <td style="padding: 1rem; color: #475569;"><?= htmlspecialchars($row['garment']) ?></td>
                        <td style="padding: 1rem; font-weight: 700; color: #1e293b;">₹<?= number_format($row['total_amount'], 2) ?></td>
                        <td style="padding: 1rem;">
                            <?php if ($row['emp_first']): ?>
                                <div style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($row['emp_first'] . ' ' . ($row['emp_last'] ?? '')) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['job_role']) ?></div>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-weight: 600; color: #334155;"><?= date('d M Y', strtotime($row['due_date'])) ?></td>
                        <td style="padding: 1rem;">
                            <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?= ucfirst($row['order_status']) ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="view-order.php?id=<?= $row['id'] ?>" class="btn btn-sm" style="background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; text-decoration: none;"><i class="ri-eye-line"></i> View</a>
                                <a href="add-order.php?id=<?= $row['id'] ?>" class="btn btn-sm" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; text-decoration: none;"><i class="ri-pencil-line"></i> Edit</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
$(document).ready(function() {
    initializeDataTable('allOrdersTable', 'All Orders Report');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success'] === "added" ? "Order created successfully" : "Success" ?>'
        });
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>