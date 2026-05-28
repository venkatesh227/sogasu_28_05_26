<?php
ob_start();
session_start();
include '../includes/db.php';

// Handle Status Toggle via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE employees SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_id'], $_POST['new_password'])) {
    $emp_id = $_POST['emp_id'];
    $new_pass = $_POST['new_password'];
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
    
    // Get user_id first
    $uStmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
    $uStmt->execute([$emp_id]);
    $user_id = $uStmt->fetchColumn();
    
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_pass, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}

// Fetch Employees
$stmt = $pdo->query("
    SELECT *
    FROM employees
    WHERE is_deleted = 0
    ORDER BY id DESC
");
$employees = $stmt->fetchAll();

// Basic Stats
$total = count($employees);
$active = 0;
foreach($employees as $e) if($e['status'] == 1) $active++;
$inactive = $total - $active;

$pageTitle = "Employee Master List - Sogasu";
$activePage = "employees";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Employee Master List</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Manage your staff records, roles, and real-time performance tracking.</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="add-employee.php" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-user-add-line"></i> Add New Employee
                </a>
            </div>
        </div>

        <!-- Compact Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;">
            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Staff</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #4f46e5; margin-top: 0.5rem;"><?= $total ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.1); color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-team-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Active Staff</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;"><?= $active ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Inactive/Off</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.5rem;"><?= $inactive ?></div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-error-warning-line"></i>
                </div>
            </div>

            <div class="table-container" style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Avg. Salary</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #8b5cf6; margin-top: 0.5rem;">₹ 24.5k</div>
                </div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-money-rupee-circle-line"></i>
                </div>
            </div>
        </div>

        <!-- Employee Table Container -->
        <div class="table-container" style="padding: 1.5rem;">
            <div style="padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Staff Directory</h3>
            </div>

            <div style="overflow-x: auto;">
                <table id="employeeTable" class="table">
                    <thead>
                        <tr>
                            <th>Employee Details</th>
                            <th>Role & Branch</th>
                            <th>Contact Info</th>
                            <th>Salary Model</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $row): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; font-size: 0.8rem; background: #e2e8f0; color: #475569; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <?= strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">ID: EMP-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['job_role']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['branch']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #1e293b;"><i class="ri-phone-line"></i> <?= htmlspecialchars($row['phone']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['email'] ?: 'No Email') ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #4f46e5;">₹ <?= number_format($row['base_salary']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($row['payment_model']) ?></div>
                                </td>
                                <td>
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?= $row['status'] == 1 ? 'checked' : '' ?> onchange="toggleEmployeeStatus(this, <?= $row['id']; ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <button class="btn-icon-p" onclick="openPassModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['first_name']) ?>')" title="Change Password" style="color: var(--warning);"><i class="ri-lock-password-line"></i></button>
                                        <a href="view-employee.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="View Profile" style="color: var(--primary);"><i class="ri-eye-line"></i></a>
                                        <a href="view-employee.php?id=<?= $row['id'] ?>#tasks-section" class="btn-icon-p" title="Tasks & Performance" style="color: var(--success);"><i class="ri-list-check"></i></a>
                                        <a href="add-employee.php?id=<?= $row['id'] ?>" class="btn-icon-p" title="Edit Record"><i class="ri-edit-line"></i></a>
                                        <button class="btn-icon-p" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete Employee" style="color: var(--danger);"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

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
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 1.1rem;
    }
    .btn-icon-p:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
        border-color: var(--primary-light);
        color: var(--primary);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleEmployeeStatus(el, id) {
        const status = el.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);

        fetch('employees.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Status Updated', timer: 1000, showConfirmButton: false });
            } else {
                el.checked = !el.checked;
                Swal.fire({ icon: 'error', title: 'Failed to update status' });
            }
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will move the employee to deleted records.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete-employee.php?id=' + id + '&redirect=employees';
            }
        });
    }
</script>

<!-- Password Reset Modal -->
<div id="passResetModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="glass-card animate-fade-in" style="width: 450px; padding: 0;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.5);">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Reset Password</h3>
            <button onclick="closePassModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem;"><i class="ri-close-line"></i></button>
        </div>
        <form id="passResetForm" onsubmit="submitPassReset(event)" style="padding: 2rem;">
            <input type="hidden" id="resetEmpId" name="emp_id">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Employee</label>
                <div id="resetEmpName" style="font-weight: 700; color: var(--primary); font-size: 1.1rem;"></div>
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">New Password</label>
                <input type="password" id="newPass" name="new_password" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; transition: border-color 0.2s;">
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Confirm Password</label>
                <input type="password" id="confirmPass" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closePassModal()" class="btn-icon-p" style="padding: 0 1.5rem; width: auto; font-weight: 700;">Cancel</button>
                <button type="submit" class="btn-premium">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPassModal(id, name) {
    document.getElementById('resetEmpId').value = id;
    document.getElementById('resetEmpName').innerText = name;
    document.getElementById('passResetModal').style.display = 'flex';
}
function closePassModal() {
    document.getElementById('passResetModal').style.display = 'none';
    document.getElementById('passResetForm').reset();
}
function submitPassReset(e) {
    e.preventDefault();
    const p1 = document.getElementById('newPass').value;
    const p2 = document.getElementById('confirmPass').value;
    const id = document.getElementById('resetEmpId').value;
    if (p1 !== p2) { Swal.fire({ icon: 'error', title: 'Passwords do not match' }); return; }
    const formData = new FormData();
    formData.append('emp_id', id);
    formData.append('new_password', p1);
    fetch('employees.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Password Updated', timer: 1500, showConfirmButton: false });
            closePassModal();
        } else {
            Swal.fire({ icon: 'error', title: 'Failed to update', text: data.error });
        }
    });
}
</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('employeeTable', 'Staff Records', 4);
    });
</script>

<?php include 'includes/footer.php'; ?>
