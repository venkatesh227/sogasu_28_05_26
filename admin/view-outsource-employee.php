<?php
ob_start();
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: outsource_employees.php");
    exit;
}

// Fetch Employee Data
$stmt = $pdo->prepare("
    SELECT * FROM employees
    WHERE id = ?
    AND is_deleted = 0
    AND employee_type = 'outsource'
");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found");
}

// Handle Certificate Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['certificate'])) {
    $cert_name = $_POST['certificate_name'] ?: 'Certificate';
    $file = $_FILES['certificate'];
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . $id . '.' . $ext;
    $target_dir = "uploads/certificates/";
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $stmt = $pdo->prepare("INSERT INTO employee_certificates (employee_id, certificate_name, file_path) VALUES (?, ?, ?)");
        $stmt->execute([$id, $cert_name, $target_file]);
        header("Location: view-outsource-employee.php?id=$id&upload=success");
        exit;
    }
}

// Handle Certificate Deletion
if (isset($_GET['delete_cert'])) {
    $cert_id = $_GET['delete_cert'];
    $stmt = $pdo->prepare("SELECT file_path FROM employee_certificates WHERE id = ? AND employee_id = ?");
    $stmt->execute([$cert_id, $id]);
    $cert = $stmt->fetch();
    
    if ($cert) {
        if (file_exists($cert['file_path'])) unlink($cert['file_path']);
        $stmt = $pdo->prepare("DELETE FROM employee_certificates WHERE id = ?");
        $stmt->execute([$cert_id]);
        header("Location: view-outsource-employee.php?id=$id&delete=success");
        exit;
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_pass = $_POST['new_password'];
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_pass, $employee['user_id']]);
    
    header("Location: view-outsource-employee.php?id=$id&reset=success");
    exit;
}

// Fetch Certificates
$certStmt = $pdo->prepare("SELECT * FROM employee_certificates WHERE employee_id = ? ORDER BY uploaded_at DESC");
$certStmt->execute([$id]);
$certificates = $certStmt->fetchAll();


// Fetch all orders/tasks assigned to this employee
$taskStmt = $pdo->prepare("
    SELECT o.*, c.first_name as cust_first, c.last_name as cust_last, sc.name as garment, r.rack_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN racks r ON o.rack_id = r.id
    WHERE o.assigned_employee_id = ? AND o.is_deleted = 0
    ORDER BY o.due_date ASC, o.id DESC
");
$taskStmt->execute([$id]);
$all_tasks = $taskStmt->fetchAll();

// Split into Active and Completed
$active_tasks = array_filter($all_tasks, function($t) { 
    return !in_array($t['order_status'], ['completed', 'delivered', 'cancelled']); 
});
$completed_tasks = array_filter($all_tasks, function($t) { 
    return in_array($t['order_status'], ['completed', 'delivered', 'cancelled']); 
});

// Calculate performance metrics
$total_tasks = count($all_tasks);
$pending_count = count($active_tasks);
$completed_count = count($completed_tasks);

$on_time_count = 0;
$delivery_performance_tasks = array_filter($completed_tasks, function($t) {
    return $t['order_status'] !== 'cancelled';
});
foreach ($delivery_performance_tasks as $t) {
    $completed_date = date('Y-m-d', strtotime($t['updated_at']));
    if ($completed_date <= $t['due_date']) {
        $on_time_count++;
    }
}
$on_time_rate = count($delivery_performance_tasks) > 0 ? round(($on_time_count / count($delivery_performance_tasks)) * 100) : 100;
$completion_rate = $total_tasks > 0 ? round(($completed_count / $total_tasks) * 100) : 100;

$pageTitle = "View Employee - " . $employee['first_name'];
$activePage = 'view-outsource-employee';
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="outsource_employees.php" class="btn-icon" style="text-decoration: none;"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                    <p class="text-muted" style="margin: 0;">Employee Profile & Documents</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="document.getElementById('passwordModal').style.display='flex'" class="btn" style="background: white; border: 1px solid #e2e8f0; color: #1e293b; cursor: pointer; padding: 0.5rem 1rem; border-radius: 6px;">
                    <i class="ri-lock-password-line"></i> Change Password
                </button>
                <a href="add-employee.php?id=<?= $id ?>" class="btn btn-primary" style="background: white; border: 1px solid #e2e8f0; color: #1e293b; text-decoration: none;">
                    <i class="ri-pencil-line"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
        <!-- Left Column: Profile Card -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; position: sticky; top: 0;">
            <div style="background: #f8fafc; padding: 2.5rem 1.5rem; text-align: center; border-bottom: 1px solid #e2e8f0;">
                <div style="width: 100px; height: 100px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 2rem; font-weight: 700; margin: 0 auto 1.5rem;">
                    <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                </div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                <p style="margin: 0.25rem 0 1rem; color: #64748b; font-size: 0.9rem;"><?= htmlspecialchars($employee['job_role']) ?> • <?= htmlspecialchars($employee['branch']) ?></p>
                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?= $employee['status'] == 1 ? '#dcfce7' : '#fee2e2' ?>; color: <?= $employee['status'] == 1 ? '#166534' : '#991b1b' ?>;">
                    <?= $employee['status'] == 1 ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Contact Information</label>
                    <div style="font-size: 0.9rem; color: #334155;"><i class="ri-phone-line" style="margin-right: 0.5rem; color: #94a3b8;"></i> <?= htmlspecialchars($employee['phone']) ?></div>
                    <div style="font-size: 0.9rem; color: #334155; margin-top: 0.25rem;"><i class="ri-mail-line" style="margin-right: 0.5rem; color: #94a3b8;"></i> <?= htmlspecialchars($employee['email'] ?: 'No Email') ?></div>
                </div>
                <div style="margin-bottom: 0;">
                    <label style="display: block; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Joining Date</label>
                    <div style="font-size: 0.9rem; color: #334155;"><i class="ri-calendar-line" style="margin-right: 0.5rem; color: #94a3b8;"></i> <?= date('d M, Y', strtotime($employee['joining_date'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Right Column: Details & Certificates -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Personal & Salary Details -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-user-settings-line" style="color: #4338ca;"></i> Salary Configuration
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px;">
                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Base Salary</label>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;">₹ <?= number_format($employee['base_salary']) ?></div>
                    </div>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px;">
                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Pay Cycle</label>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($employee['pay_cycle']) ?></div>
                    </div>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px;">
                        <label style="display: block; font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Payment Model</label>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($employee['payment_model']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Certificates Section -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-file-list-3-line" style="color: #4338ca;"></i> Employee Certificates
                    </h3>
                    <button onclick="document.getElementById('certUploadModal').style.display='flex'" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                        <i class="ri-upload-2-line"></i> Upload New
                    </button>
                </div>
                
                <div style="padding: 1.5rem;">
                    <?php if (empty($certificates)): ?>
                        <div style="text-align: center; padding: 3rem 0;">
                            <i class="ri-file-history-line" style="font-size: 3rem; color: #e2e8f0;"></i>
                            <p style="color: #94a3b8; margin-top: 1rem;">No certificates uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                            <?php foreach ($certificates as $cert): 
                                $ext = strtolower(pathinfo($cert['file_path'], PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                            ?>
                                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; position: relative; transition: all 0.2s;" onmouseover="this.style.borderColor='#4338ca'" onmouseout="this.style.borderColor='#e2e8f0'">
                                    <div style="width: 100%; height: 120px; background: #f8fafc; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; overflow: hidden;">
                                        <?php if ($isImg): ?>
                                            <img src="<?= $cert['file_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="ri-file-pdf-line" style="font-size: 2.5rem; color: #ef4444;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($cert['certificate_name']) ?>">
                                        <?= htmlspecialchars($cert['certificate_name']) ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem;">Uploaded: <?= date('d M, Y', strtotime($cert['uploaded_at'])) ?></div>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                                        <a href="<?= $cert['file_path'] ?>" target="_blank" class="btn" style="flex: 1; padding: 0.3rem; font-size: 0.75rem; background: #f1f5f9; text-decoration: none; text-align: center; color: #475569;">View</a>
                                        <button onclick="confirmDeleteCert(<?= $cert['id'] ?>)" class="btn" style="padding: 0.3rem 0.5rem; font-size: 0.75rem; background: #fee2e2; color: #991b1b; border: none;">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tasks & Performance Monitor Section -->
            <div id="tasks-section" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-top: 1rem;">
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-list-check" style="color: #4f46e5;"></i> Tasks & Performance Monitor
                    </h3>
                </div>
                
                <div style="padding: 1.5rem;">
                    <!-- Metrics Row -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="background: #f3e8ff; border: 1px solid #d8b4fe; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.75rem; color: #6b21a8; font-weight: 700; text-transform: uppercase;">Total Tasks</div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: #6b21a8; margin-top: 0.25rem;"><?= $total_tasks ?></div>
                        </div>
                        <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.75rem; color: #92400e; font-weight: 700; text-transform: uppercase;">Active Tasks</div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: #92400e; margin-top: 0.25rem;"><?= $pending_count ?></div>
                        </div>
                        <div style="background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.75rem; color: #166534; font-weight: 700; text-transform: uppercase;">Completed</div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: #166534; margin-top: 0.25rem;"><?= $completed_count ?></div>
                        </div>
                        <div style="background: #e0e7ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 0.85rem; text-align: center;">
                            <div style="font-size: 0.75rem; color: #3730a3; font-weight: 700; text-transform: uppercase;">On-Time Delivery</div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: #3730a3; margin-top: 0.25rem;"><?= $on_time_rate ?>%</div>
                        </div>
                    </div>

                    <!-- Performance Index Bar -->
                    <div style="background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; font-weight: 700; color: #475569;">Employee Performance Index</span>
                            <span style="font-size: 1rem; font-weight: 800; color: #4f46e5;"><?= $completion_rate ?>%</span>
                        </div>
                        <div style="height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; display: flex;">
                            <div style="width: <?= $completion_rate ?>%; height: 100%; background: linear-gradient(90deg, #4f46e5, #818cf8); border-radius: 999px;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                            <span>Completion Velocity</span>
                            <span><?= $completed_count ?> / <?= $total_tasks ?> Orders Completed</span>
                        </div>
                    </div>

                    <!-- Tab Buttons -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; background: #f1f5f9; padding: 0.25rem; border-radius: 8px;">
                        <button id="btnTaskTab1" type="button" onclick="switchEmployeeTaskTab(1)" style="flex: 1; border: none; background: white; color: #1e293b; font-weight: 700; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s;">
                            Active Workload (<?= count($active_tasks) ?>)
                        </button>
                        <button id="btnTaskTab2" type="button" onclick="switchEmployeeTaskTab(2)" style="flex: 1; border: none; background: transparent; color: #64748b; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                            Task History (<?= count($completed_tasks) ?>)
                        </button>
                    </div>

                    <!-- Active Tasks Tab -->
                    <div id="taskTabContainer1" style="display: block;">
                        <?php if (empty($active_tasks)): ?>
                            <div style="text-align: center; padding: 2.5rem 1.5rem; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8;">
                                <i class="ri-checkbox-circle-line" style="font-size: 2.5rem; color: #10b981; display: block; margin-bottom: 0.75rem;"></i>
                                <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">All Caught Up!</div>
                                <p style="font-size: 0.8rem; color: #64748b; margin: 0;">No active tasks assigned to this employee.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                            <th style="padding: 0.75rem 1rem;">Garment / Style</th>
                                            <th style="padding: 0.75rem 1rem;">Order Code</th>
                                            <th style="padding: 0.75rem 1rem;">Customer</th>
                                            <th style="padding: 0.75rem 1rem;">Due Date</th>
                                            <th style="padding: 0.75rem 1rem;">Status</th>
                                            <th style="padding: 0.75rem 1rem;">Rack</th>
                                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_tasks as $task): 
                                            $isOverdue = strtotime($task['due_date']) < time();
                                            $dueColor = $isOverdue ? '#ef4444' : '#475569';
                                            $dueWeight = $isOverdue ? '800' : '600';
                                        ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($task['garment']) ?></td>
                                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: #4338ca;">#<?= htmlspecialchars($task['order_code']) ?></td>
                                                <td style="padding: 0.75rem 1rem; color: #475569; font-weight: 500;"><?= htmlspecialchars($task['cust_first'] . ' ' . $task['cust_last']) ?></td>
                                                <td style="padding: 0.75rem 1rem; color: <?= $dueColor ?>; font-weight: <?= $dueWeight ?>;">
                                                    <?= date('d M, Y', strtotime($task['due_date'])) ?>
                                                    <?php if ($isOverdue): ?>
                                                        <span style="font-size: 0.65rem; background: #fee2e2; color: #ef4444; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; margin-left: 4px;">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 0.75rem 1rem;">
                                                    <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; background: #fffbeb; color: #d97706; border: 1px solid #fef3c7;">
                                                        <?= ucfirst($task['order_status']) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; color: #64748b; font-weight: 600;">
                                                    <?= $task['rack_name'] ? htmlspecialchars($task['rack_name']) : '<span style="color:#ef4444; font-style:italic;">None</span>' ?>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                                    <a href="view-order.php?id=<?= $task['id'] ?>" class="btn" style="background: #f1f5f9; color: #4f46e5; text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block;">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Completed Tasks Tab -->
                    <div id="taskTabContainer2" style="display: none;">
                        <?php if (empty($completed_tasks)): ?>
                            <div style="text-align: center; padding: 2.5rem 1.5rem; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8;">
                                <i class="ri-history-line" style="font-size: 2.5rem; color: #94a3b8; display: block; margin-bottom: 0.75rem;"></i>
                                <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">No History</div>
                                <p style="font-size: 0.8rem; color: #64748b; margin: 0;">No completed tasks recorded for this employee.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                            <th style="padding: 0.75rem 1rem;">Garment / Style</th>
                                            <th style="padding: 0.75rem 1rem;">Order Code</th>
                                            <th style="padding: 0.75rem 1rem;">Customer</th>
                                            <th style="padding: 0.75rem 1rem;">Completion Date</th>
                                            <th style="padding: 0.75rem 1rem;">Performance</th>
                                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completed_tasks as $task): 
                                            $completed_date = date('Y-m-d', strtotime($task['updated_at']));
                                            $isOnTime = $completed_date <= $task['due_date'];
                                            $perfBg = $isOnTime ? '#dcfce7' : '#fee2e2';
                                            $perfColor = $isOnTime ? '#15803d' : '#b91c1c';
                                            $perfText = $isOnTime ? 'On Time' : 'Delayed';
                                        ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9; opacity: 0.9;">
                                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: #1e293b; text-decoration: line-through; opacity: 0.7;"><?= htmlspecialchars($task['garment']) ?></td>
                                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: #4338ca;">#<?= htmlspecialchars($task['order_code']) ?></td>
                                                <td style="padding: 0.75rem 1rem; color: #475569; font-weight: 500;"><?= htmlspecialchars($task['cust_first'] . ' ' . $task['cust_last']) ?></td>
                                                <td style="padding: 0.75rem 1rem; color: #64748b; font-weight: 600;"><?= date('d M, Y', strtotime($task['updated_at'])) ?></td>
                                                <td style="padding: 0.75rem 1rem;">
                                                    <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; background: <?= $perfBg ?>; color: <?= $perfColor ?>;">
                                                        <?= $perfText ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                                    <a href="view-order.php?id=<?= $task['id'] ?>" class="btn" style="background: #f1f5f9; color: #4f46e5; text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block;">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Upload Modal -->
<div id="certUploadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; width: 450px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Upload Certificate</h3>
            <button onclick="document.getElementById('certUploadModal').style.display='none'" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;"><i class="ri-close-line"></i></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Certificate Name</label>
                <input type="text" name="certificate_name" placeholder="e.g. Training Certificate" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Select File (Image or PDF)</label>
                <input type="file" name="certificate" accept="image/*,.pdf" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('certUploadModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.5rem; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 6px;">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Password Reset Modal -->
<div id="passwordModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; width: 400px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Change Password</h3>
            <button onclick="document.getElementById('passwordModal').style.display='none'" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;"><i class="ri-close-line"></i></button>
        </div>
        <form action="" method="POST" style="padding: 1.5rem;" onsubmit="return validatePassword()">
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">New Password</label>
                <input type="password" name="new_password" id="new_password" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Confirm Password</label>
                <input type="password" id="confirm_password" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.5rem; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 6px;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function validatePassword() {
        const p1 = document.getElementById('new_password').value;
        const p2 = document.getElementById('confirm_password').value;
        if (p1 !== p2) {
            Swal.fire({ icon: 'error', title: 'Passwords do not match' });
            return false;
        }
        return true;
    }

    <?php if (isset($_GET['reset'])): ?>
        Swal.fire({ icon: 'success', title: 'Password Changed', text: 'Employee can now login with the new password.', timer: 2000 });
    <?php endif; ?>
    function confirmDeleteCert(certId) {
        Swal.fire({
            title: 'Delete Certificate?',
            text: "This file will be permanently removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'view-employee.php?id=<?= $id ?>&delete_cert=' + certId;
            }
        });
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('certUploadModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function switchEmployeeTaskTab(tabIndex) {
        const tab1 = document.getElementById('taskTabContainer1');
        const tab2 = document.getElementById('taskTabContainer2');
        const btn1 = document.getElementById('btnTaskTab1');
        const btn2 = document.getElementById('btnTaskTab2');
        
        if (tabIndex === 1) {
            tab1.style.display = 'block';
            tab2.style.display = 'none';
            btn1.style.background = 'white';
            btn1.style.color = '#1e293b';
            btn1.style.fontWeight = '700';
            btn1.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
            
            btn2.style.background = 'transparent';
            btn2.style.color = '#64748b';
            btn2.style.fontWeight = '600';
            btn2.style.boxShadow = 'none';
        } else {
            tab1.style.display = 'none';
            tab2.style.display = 'block';
            btn2.style.background = 'white';
            btn2.style.color = '#1e293b';
            btn2.style.fontWeight = '700';
            btn2.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
            
            btn1.style.background = 'transparent';
            btn1.style.color = '#64748b';
            btn1.style.fontWeight = '600';
            btn1.style.boxShadow = 'none';
        }
    }
</script>

<style>
    .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; }
    .btn-icon:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
</style>

<?php include 'includes/footer.php'; ?>
