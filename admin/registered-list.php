<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$activePage = 'registered-list';
$pageTitle = 'Registered List - Sogasu';

/* DELETE HANDLE */
if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];

    $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
    $stmt->execute([$deleteId]);
    $userId = $stmt->fetchColumn();

    // employees table soft delete
    $stmt = $pdo->prepare("UPDATE employees SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$deleteId]);

    // users table disable
    $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE id = ?");
    $stmt->execute([$userId]);

    header("Location: registered-list.php?deleted=1");
    exit;
}

/* FETCH REGISTERED EMPLOYEES */
$stmt = $pdo->query("
    SELECT 
        e.*,
        u.username,
        u.is_registered
    FROM employees e
    INNER JOIN users u ON u.id = e.user_id
    WHERE e.is_deleted = 0
        AND u.is_registered = 1
        AND (e.job_role IS NULL OR e.job_role = '')
    ORDER BY e.id DESC
");
$employees = $stmt->fetchAll();

$totalEmployees = count($employees);

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM employees e
    INNER JOIN users u ON u.id = e.user_id
    WHERE e.is_deleted = 0
        AND u.is_registered = 1
        AND (e.job_role IS NULL OR e.job_role = '')
        AND e.status = 1
");
$activeEmployees = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM employees e
    INNER JOIN users u ON u.id = e.user_id
    WHERE e.is_deleted = 0
        AND u.is_registered = 1
        AND (e.job_role IS NULL OR e.job_role = '')
        AND DATE(e.created_at) = CURDATE()
");
$todayRegistrations = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM employees e
    INNER JOIN users u ON u.id = e.user_id
    WHERE e.is_deleted = 0
        AND u.is_registered = 1
        AND (e.job_role IS NULL OR e.job_role = '')
        AND MONTH(e.created_at) = MONTH(CURDATE())
        AND YEAR(e.created_at) = YEAR(CURDATE())
");
$monthRegistrations = $stmt->fetchColumn();

include 'includes/header.php';
?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="font-size:1.5rem; font-weight:700; margin:0;">
                    Registered Employees
                </h2>
                <p style="color:#64748b; margin-top:0.25rem;">
                    View all registered employee accounts
                </p>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-team-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $totalEmployees ?></h3>
                    <p>Total Registered</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active-icon">
                    <i class="ri-user-follow-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $activeEmployees ?></h3>
                    <p>Active Employees</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon today-icon">
                    <i class="ri-calendar-check-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $todayRegistrations ?></h3>
                    <p>Today Registrations</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon month-icon">
                    <i class="ri-bar-chart-box-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $monthRegistrations ?></h3>
                    <p>This Month</p>
                </div>
            </div>
        </div>

        <div class="table-container" style="padding:1.5rem; margin-top:20px;">
            <div style="overflow-x:auto;">
                <table id="registeredTable" class="table">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($employees as $row): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div style="
                                            width:36px;
                                            height:36px;
                                            border-radius:50%;
                                            background:#e2e8f0;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            font-weight:700;
                                        ">
                                            <?= strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'] ?? '', 0, 1)) ?>
                                        </div>

                                        <div>
                                            <div style="font-weight:700;">
                                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                            </div>
                                            <div style="font-size:0.75rem; color:#64748b;">
                                                ID: EMP-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['phone']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['email']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['address'] ?: 'N/A') ?>
                                </td>

                                <td style="text-align:right;">
                                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                                        <a href="add-employee.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>

                                        <button class="btn-icon-p" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete"
                                            style="color:red;">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-icon-p {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #f1f5f9;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-icon-p:hover {
        background: white;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin: 20px 0;
    }

    .stat-card {
        background: white;
        border-radius: 18px;
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        border: 1px solid #eef2f7;
        transition: all 0.25s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: #eef2ff;
        color: #1a237e;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .active-icon {
        background: #ecfdf3;
        color: #16a34a;
    }

    .today-icon {
        background: #fff7ed;
        color: #ea580c;
    }

    .month-icon {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }

    .stat-content p {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 14px;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "Employee will be deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'registered-list.php?delete_id=' + id;
            }
        });
    }
</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function () {
        initializeDataTable('registeredTable', 'Registered Employees', 4);
    });
</script>
<?php if (isset($_GET['deleted'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Deleted Successfully',
            text: 'Employee deleted successfully.',
            confirmButtonColor: '#16a34a'
        });
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>