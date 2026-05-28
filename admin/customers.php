<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all customers for DataTables (removing manual pagination for better experience)
$query = "SELECT customers.*, users.status, 
          (SELECT COUNT(*) FROM customer_family_members WHERE customer_id = customers.id AND is_deleted = 0) as family_count 
          FROM customers 
          INNER JOIN users ON customers.user_id = users.id 
          WHERE customers.is_deleted = 0 
          ORDER BY customers.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$customers = $stmt->fetchAll();

// Stats for cards
$totalCount = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_deleted=0")->fetchColumn();
$inactiveCount = $pdo->query("SELECT COUNT(*) FROM customers INNER JOIN users ON customers.user_id = users.id WHERE customers.is_deleted=0 AND users.status=0")->fetchColumn();
$activeCount = $totalCount - $inactiveCount;

$pageTitle = "Customers - Sogasu";
$activePage = "customers";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Customer Management</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Manage client relationships and family profiles</p>
            </div>
            <a href="add-customer.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white;">
                <i class="ri-user-add-line"></i> Add New Customer
            </a>
        </div>

        <!-- Compact Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="table-container" style="padding: 1.25rem; margin-top: 0;">
                <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Customers</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: #1e293b; margin-top: 0.5rem;"><?= $totalCount ?></div>
            </div>
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; border-left: 4px solid #10b981;">
                <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Active Clients</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;"><?= $activeCount ?></div>
            </div>
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; border-left: 4px solid #ef4444;">
                <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Inactive</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.5rem;"><?= $inactiveCount ?></div>
            </div>
        </div>

        <!-- Customers Table Card -->
        <div class="table-container">
            <table id="customersTable" class="table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Contact Info</th>
                        <th>Location</th>
                        <th>Family Members</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $row): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem;">
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8;">UID: #<?= $row['id'] ?></div>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 600; color: #475569;"><i class="ri-phone-line"></i> <?= htmlspecialchars($row['phone']) ?></div>
                            <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['email'] ?: 'No Email') ?></div>
                        </td>
                        <td style="padding: 1rem; color: #64748b; font-size: 0.85rem;">
                            <?= htmlspecialchars($row['area'] . ', ' . $row['city']) ?>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <a href="customer-family.php?id=<?= $row['id'] ?>" style="text-decoration: none; background: #eef2ff; color: #6366f1; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                <i class="ri-parent-line"></i> <?= $row['family_count'] ?> Members
                            </a>
                        </td>
                        <td style="padding: 1rem;">
                            <?php $statusColor = ($row['status'] == 1) ? '#10b981' : '#ef4444'; ?>
                            <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">
                                <?= ($row['status'] == 1) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="view-customer.php?id=<?= $row['id'] ?>" style="color: #6366f1; font-size: 1.1rem;"><i class="ri-eye-line"></i></a>
                                <a href="add-customer.php?id=<?= $row['id'] ?>" style="color: #64748b; font-size: 1.1rem;"><i class="ri-edit-line"></i></a>
                                <a href="#" onclick="confirmDelete(<?= $row['id'] ?>)" style="color: #ef4444; font-size: 1.1rem;"><i class="ri-delete-bin-line"></i></a>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>
$(document).ready(function() {
    initializeDataTable('customersTable', 'Customers List');
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete Customer?',
        text: "This will remove the customer and their history.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete-customer.php?id=' + id;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>