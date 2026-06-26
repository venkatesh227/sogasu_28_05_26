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
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Include translations
require_once __DIR__ . '/includes/translations.php';
$t = $translations[$language] ?? $translations['en'];

// Fetch employee data and job role
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.preferred_language, e.job_role, e.pay_cycle, e.employee_type
    FROM employees e 
    WHERE e.user_id = ? AND e.is_deleted = 0
");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();
if (!$emp) {
    header("Location: login.php");
    exit();
}

if ($emp['employee_type'] !== 'outsource') {
    header("Location: dashboard.php");
    exit();
}
$employee_id = $emp['id'];
$employee_name = trim($emp['first_name'] . ' ' . $emp['last_name']);
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM outsource_orders
    WHERE is_deleted = 0
");
$stmt->execute();
$total_outsource_orders = $stmt->fetchColumn();

// Fetch Active Tasks (Urgent first)
$stmt = $pdo->prepare("
    SELECT 
        oo.*,
        sc.name AS sub_category_name
    FROM outsource_orders oo
    LEFT JOIN sub_categories sc 
        ON sc.id = oo.sub_category_id
    WHERE oo.order_status IN ('pending', 'accepted')
    AND oo.is_deleted = 0
    ORDER BY oo.created_at DESC
    LIMIT 10
");
$stmt->execute();
$active_tasks = $stmt->fetchAll();
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM outsource_orders
    WHERE order_status IN ('accepted','approved','in progress')
    AND is_deleted = 0
");
$stmt->execute();
$activeTasksCount = $stmt->fetchColumn();

$pageTitle = "Outsourcing Dashboard - Sogasu";
$headerTitle = "Outsource Portal";
$activePage = "dashboard";

$notifStmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE employee_id = ?
    AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 1
");
$notifStmt->execute([$employee_id]);
$popupNotification = $notifStmt->fetch();

if ($popupNotification) {
    $markReadStmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
    ");
    $markReadStmt->execute([$popupNotification['id']]);
}

include 'includes/outsource-header.php';

?>

<div class="container" style="padding-bottom: 100px;">
    <!-- Top Summary Card -->
    <div class="card"
        style="background: linear-gradient(135deg, #db2777, #9d174d); border: none; padding: 1.5rem; color: white; border-radius: 20px; position: relative; overflow: hidden; margin-bottom: 1.5rem;">
        <div style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 0.25rem;">
            Total Outsource Orders: <?= $total_outsource_orders ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <div style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.1rem;">Active Tasks</div>
                <div style="font-size: 1.1rem; font-weight: 700;"><?= $activeTasksCount ?> Jobs</div>
            </div>
            <button onclick="window.location.href='outsource-orders.php'"
                style="background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3); color: white; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600;">View
                All <i class="ri-arrow-right-s-line"></i></button>
        </div>

        <!-- Decoration -->
        <i class="ri-wallet-3-line"
            style="position: absolute; right: -10px; top: -10px; font-size: 5rem; opacity: 0.15; transform: rotate(-15deg);"></i>
    </div>

    <!-- Active Tasks Section -->
    <div class="section-title">
        <span>Available Outsource Orders</span>
    </div>

    <?php if (empty($active_tasks)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; border-style: dashed;">
            <div
                style="width: 60px; height: 60px; background: #f0fdf4; color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ri-checkbox-circle-fill" style="font-size: 2rem;"></i>
            </div>
            <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">No outsource orders available</div>
        </div>
    <?php else: ?>
        <?php foreach ($active_tasks as $job): ?>
            <?php
            $responseStmt = $pdo->prepare("
                SELECT id
                FROM outsource_order_responses
                WHERE order_id = ?
                AND employee_id = ?
            ");
            $responseStmt->execute([$job['id'], $employee_id]);
            $alreadyResponded = $responseStmt->fetch();
            ?>
            <div class="card" style="
                padding:20px;
                margin-bottom:16px;
                border-radius:20px;
                box-shadow:0 10px 25px rgba(0,0,0,.06);
                border:1px solid #fce7f3;
                background:white;
            ">

                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-weight:700;font-size:18px;color:#1e293b;">
                        <?= htmlspecialchars($job['order_code']) ?>
                    </div>

                    <span style="
                        background:#fef3c7;
                        color:#b45309;
                        padding:6px 12px;
                        border-radius:999px;
                        font-size:12px;
                        font-weight:700;">
                        <?= ucfirst(str_replace('_', ' ', $job['order_status'])) ?>
                    </span>
                </div>

                <div style="margin-top:14px;color:#64748b;font-size:14px;">
                    <div><b>Order ID:</b> #<?= htmlspecialchars($job['order_code']) ?></div>
                    <div style="margin-top:4px;">
                        <b>Created:</b> <?= date('d M Y', strtotime($job['created_at'])) ?>
                    </div>
                    <?php if (!empty($job['sub_category_name'])): ?>
                        <div style="margin-top:4px;">
                            <b>Garment:</b>
                            <?= htmlspecialchars($job['sub_category_name']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($job['due_date'])): ?>
                        <div style="margin-top:4px;">
                            <b>Due:</b> <?= date('d M Y', strtotime($job['due_date'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($job['fabric_details'])): ?>
                        <div style="margin-top:4px;">
                            <b>Fabric:</b> <?= htmlspecialchars(substr($job['fabric_details'], 0, 40)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; gap:10px; margin-top:18px;">
                    <?php if (in_array($job['order_status'], ['pending', 'accepted']) && !$alreadyResponded): ?>

                        <a href="#" onclick="confirmAccept(<?= $job['id'] ?>)" style="
                            text-decoration:none;
                            background:#22c55e;
                            color:white;
                            padding:10px 18px;
                            border-radius:12px;
                            font-size:14px;
                            font-weight:600;">
                            Accept
                        </a>

                        <a href="#" onclick="confirmReject(<?= $job['id'] ?>)" style="
                            text-decoration:none;
                            background:#ef4444;
                            color:white;
                            padding:10px 18px;
                            border-radius:12px;
                            font-size:14px;
                            font-weight:600;">
                            Reject
                        </a>
                    <?php elseif ($alreadyResponded): ?>
                        <div style="
                            color:#64748b;
                            font-size:13px;
                            font-weight:600;
                            padding:10px 0;">
                            Response already submitted
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:20px;">

        <div onclick="location.href='outsource-orders.php'" style="
        background:white;
        padding:18px 12px;
        border-radius:18px;
        box-shadow:0 8px 25px rgba(0,0,0,.06);
        text-align:center;
        cursor:pointer;
        border:1px solid #fce7f3;
    ">
            <i class="ri-time-line" style="font-size:24px;color:#f59e0b;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Pending</div>
        </div>

        <div onclick="location.href='outsource-orders.php?tab=accepted'" style="
        background:white;
        padding:18px 12px;
        border-radius:18px;
        box-shadow:0 8px 25px rgba(0,0,0,.06);
        text-align:center;
        cursor:pointer;
        border:1px solid #fce7f3;
    ">
            <i class="ri-checkbox-circle-line" style="font-size:24px;color:#22c55e;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Accepted</div>
        </div>

        <div onclick="location.href='outsource-notifications.php'" style="
        background:white;
        padding:18px 12px;
        border-radius:18px;
        box-shadow:0 8px 25px rgba(0,0,0,.06);
        text-align:center;
        cursor:pointer;
        border:1px solid #fce7f3;
    ">
            <i class="ri-notification-3-line" style="font-size:24px;color:#db2777;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Alerts</div>
        </div>

    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:14px;">

        <div onclick="location.href='outsource-earnings.php'" style="
        background:white;
        padding:18px 12px;
        border-radius:18px;
        box-shadow:0 8px 25px rgba(0,0,0,.06);
        text-align:center;
        cursor:pointer;
        border:1px solid #fce7f3;
    ">
            <i class="ri-coins-line" style="font-size:24px;color:#22c55e;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Earnings</div>
        </div>

        <div onclick="location.href='outsource-payments.php'" style="
        background:white;
        padding:18px 12px;
        border-radius:18px;
        box-shadow:0 8px 25px rgba(0,0,0,.06);
        text-align:center;
        cursor:pointer;
        border:1px solid #fce7f3;
    ">
            <i class="ri-bank-card-line" style="font-size:24px;color:#3b82f6;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Payments</div>
        </div>

        <div onclick="location.href='outsource-ledger.php'" style="
            background:white;
            padding:18px 12px;
            border-radius:18px;
            box-shadow:0 8px 25px rgba(0,0,0,.06);
            text-align:center;
            cursor:pointer;
            border:1px solid #fce7f3;
        ">
            <i class="ri-book-2-line" style="font-size:24px;color:#a855f7;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">Ledger</div>
        </div>
        <div onclick="location.href='outsource-advance-history.php'" style="
            background:white;
            padding:18px 12px;
            border-radius:18px;
            box-shadow:0 8px 25px rgba(0,0,0,.06);
            text-align:center;
            cursor:pointer;
            border:1px solid #fce7f3;
        ">
            <i class="ri-exchange-funds-line" style="font-size:24px;color:#f97316;"></i>
            <div style="margin-top:8px;font-size:13px;font-weight:600;">
                Advance
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    @keyframes slideUp {
        from {
            transform: translateY(100%);
        }

        to {
            transform: translateY(0);
        }
    }

    .btn-action:active {
        transform: scale(0.98);
    }
</style>

<?php if ($popupNotification): ?>
    <script>
        Swal.fire({
            icon: 'info',
            title: 'New Outsource Order',
            text: <?= json_encode($popupNotification['message']) ?>,
            confirmButtonColor: '#db2777'
        });
    </script>
<?php endif; ?>
<script>
    function confirmAccept(id) {
        Swal.fire({
            title: 'Accept Order?',
            text: 'Do you want to accept this order?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#22c55e',
            confirmButtonText: 'Yes, Accept'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location =
                    'handle-outsource-order.php?id=' + id + '&action=accept';
            }
        });
    }

    function confirmReject(id) {
        Swal.fire({
            title: 'Reject Order?',
            text: 'Do you want to reject this order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Reject'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location =
                    'handle-outsource-order.php?id=' + id + '&action=reject';
            }
        });
    }
</script>
<?php if ($success === 'accepted'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Interest Submitted Successfully',
            text: 'Your interest in this order has been recorded. Admin will review and assign the order accordingly.',
            confirmButtonColor: '#db2777'
        }).then(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>

<?php if ($success === 'rejected'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'All Employees Rejected',
            text: 'Order moved to rejected'
        }).then(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>

<?php if ($success === 'response_saved'): ?>
    <script>
        Swal.fire({
            icon: 'info',
            title: 'Response Saved',
            text: 'Waiting for other employees'
        }).then(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>

<?php if ($error === 'already_responded'): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Already Responded'
        });
    </script>
<?php endif; ?>

<?php include 'includes/outsource-bottom-nav.php'; ?>