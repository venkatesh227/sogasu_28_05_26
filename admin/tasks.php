<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Map tabs to statuses
$statusMap = [
    'Embroidery' => 'embroidery',
    'Cutting' => 'cutting',
    'Sewing' => 'stitching',
    'Finishes' => 'finishing',
    'Aari Work' => 'pattern_making' // Assuming Aari Work is handled in Pattern/Pattern making for now
];

$activeTab = $_GET['tab'] ?? 'Embroidery';
$dbStatus = $statusMap[$activeTab] ?? 'embroidery';

$pageTitle = "Task Management - Sogasu";
$activePage = "tasks";

// Fetch Employees for Assignment
$employees = $pdo->query("SELECT id, first_name, last_name, job_role FROM employees WHERE is_deleted = 0 AND status = 'active' ORDER BY first_name ASC")->fetchAll();

// Fetch Real Tasks (Orders in this status)
$stmt = $pdo->prepare("
    SELECT o.id, o.order_code, o.order_status, o.assigned_employee_id, o.due_date,
           c.first_name as cust_first, c.last_name as cust_last,
           sc.name as garment
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    WHERE o.order_status = ? AND o.is_deleted = 0
    ORDER BY o.due_date ASC
");
$stmt->execute([$dbStatus]);
$tasksList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Task Management</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Monitor workshop operations and track employee productivity</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-light" onclick="location.reload()" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Task Categories (Functional Tabs) -->
        <style>
            .status-tab {
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
                white-space: nowrap;
            }
            .status-tab:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
        </style>
        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem; ">
            <?php foreach($statusMap as $tabName => $statusValue): ?>
                <a href="?tab=<?= urlencode($tabName) ?>" 
                   class="status-tab" 
                   style="background: <?= ($activeTab == $tabName) ? '#4f46e5' : '#ffffff' ?>; color: <?= ($activeTab == $tabName) ? 'white' : '#64748b' ?>; border: 1px solid <?= ($activeTab == $tabName) ? '#4f46e5' : '#e2e8f0' ?>;">
                    <?= $tabName ?> (<?= $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = '$statusValue' AND is_deleted = 0")->fetchColumn() ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 1.5rem;">
            
            <!-- Active Tasks Card -->
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Active <?= $activeTab ?> Tasks</h3>
                </div>

                <table id="tasksTable" class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer / Garment</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tasksList)): ?>
                            <?php foreach($tasksList as $t): ?>
                            <tr>
                                <td style="padding: 1rem; font-weight: 700; color: #4f46e5;">#<?= htmlspecialchars($t['order_code']) ?></td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($t['cust_first'] . ' ' . $t['cust_last']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($t['garment'] ?? 'General') ?></div>
                                </td>
                                <td style="padding: 1rem;">
                                    <select class="form-select assignment-select" data-id="<?= $t['id'] ?>" style="font-size: 0.8rem; padding: 4px 8px; height: 32px; border-radius: 6px; width: 160px;">
                                        <option value="">Unassigned</option>
                                        <?php foreach($employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= ($t['assigned_employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars($emp['job_role']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="padding: 1rem; font-weight: 600; color: #64748b;">
                                    <i class="ri-calendar-line"></i> <?= date('d M Y', strtotime($t['due_date'])) ?>
                                </td>
                                <td style="padding: 1rem; text-align: right;">
                                    <button class="btn btn-sm btn-done" data-id="<?= $t['id'] ?>" style="background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                        MARK DONE
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Productivity Stats Card (Dynamic-ish) -->
            <div class="table-container" style="align-self: start;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-line-chart-line" style="color: #6366f1;"></i> Productivity
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php
                    $totalActive = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('cutting','embroidery','stitching','finishing') AND is_deleted=0")->fetchColumn();
                    $totalToday = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'ready' AND DATE(updated_at) = CURDATE()")->fetchColumn();
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Overall Progress</span>
                            <span style="font-size: 0.85rem; color: #1e293b; font-weight: 800;">72%</span>
                        </div>
                        <div style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <div style="width: 72%; height: 100%; background: #6366f1;"></div>
                        </div>
                    </div>

                    <div style="background: #f8fafc; padding: 1rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">Workshop Stats</div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 1.25rem; font-weight: 800; color: #1e293b;"><?= $totalActive ?></div>
                                <div style="font-size: 0.7rem; color: #64748b;">In Production</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.25rem; font-weight: 800; color: #10b981;"><?= $totalToday ?></div>
                                <div style="font-size: 0.7rem; color: #64748b;">Ready Today</div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                        <h4 style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">Task Distribution</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach($statusMap as $label => $val): 
                                $count = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = '$val' AND is_deleted=0")->fetchColumn();
                            ?>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.8rem; color: #475569; font-weight: 600;"><?= $label ?></span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #1e293b;"><?= $count ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>
$(document).ready(function() {
    initializeDataTable('tasksTable', '<?= $activeTab ?> Tasks Report');

    // Handle Employee Assignment
    $('.assignment-select').on('change', function() {
        const orderId = $(this).data('id');
        const empId = $(this).val();
        
        $.ajax({
            url: 'update-task.php',
            method: 'POST',
            data: { action: 'assign', order_id: orderId, employee_id: empId },
            success: function(response) {
                const res = JSON.parse(response);
                if(res.success) {
                    Swal.fire({ icon: 'success', title: 'Assigned', text: 'Employee assigned successfully', timer: 1000, showConfirmButton: false });
                }
            }
        });
    });

    // Handle Mark Done
    $('.btn-done').on('click', function() {
        const orderId = $(this).data('id');
        const row = $(this).closest('tr');
        
        Swal.fire({
            title: 'Move to next stage?',
            text: "This will move the task to the next logical production stage.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, Mark Done!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'update-task.php',
                    method: 'POST',
                    data: { action: 'done', order_id: orderId },
                    success: function(response) {
                        const res = JSON.parse(response);
                        if(res.success) {
                            row.fadeOut(300, function() { location.reload(); });
                        }
                    }
                });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>