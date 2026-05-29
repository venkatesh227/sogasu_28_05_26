<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payroll.php");
    exit;
}

$stmt = $pdo->prepare("SELECT e.*, j.role_name FROM employees e LEFT JOIN job_roles j ON e.job_role = j.id WHERE e.id = ? AND e.is_deleted=0");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    header("Location: payroll.php");
    exit;
}



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ot_date = $_POST['ot_date'] ?? date('Y-m-d');
    $hours = floatval($_POST['hours'] ?? 0);
$description = $_POST['description'] ?? '';

$otStmt = $pdo->prepare("
    SELECT ot_percentage
    FROM ot_rate_settings
    WHERE ? BETWEEN from_date AND to_date
    LIMIT 1
");
$otStmt->execute([$ot_date]);

$otPercentage = $otStmt->fetchColumn() ?: 0;

$salaryAmount = floatval($employee['base_salary']);

// OT is calculated as a percentage of the employee's salary for the selected date range.
// Hours are recorded for tracking, but the payout is based on salary percentage.
$amount = ($salaryAmount * $otPercentage) / 100;
    // Auto-approve if entered by Admin or Supervisor
    $status = (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'supervisor')) ? 'Approved' : 'Pending';
    
    if ($hours > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_overtime
(employee_id, ot_date, hours, amount, description, status)
VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $id,
    $ot_date,
    $hours,
    $amount,
    $description,
    $status
]);        
        $_SESSION['success'] = "Overtime logged successfully for " . htmlspecialchars($employee['first_name']);
    }
    
    header("Location: employee-history.php?id=" . $id);
    exit;
}

$pageTitle = "Add Overtime - Sogasu";
$activePage = "payroll";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                 <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Add Overtime</h2>
                    <span style="background: #f0f9ff; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">OT LOG</span>
                 </div>
                 <p class="text-muted">Recording extra hours for <strong><?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?></strong></p>
            </div>
            <button class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Cancel
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        
        <!-- Left Column: OT Details -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <div style="background: white; border: 1px solid #e2e8f0; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-time-line" style="color: #6366f1;"></i> Logged Hours
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Work Date</label>
                        <div style="position: relative;">
                            <i class="ri-calendar-event-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <input type="date" name="ot_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" style="padding-left: 2.5rem;">
                        </div>
                    </div>
                     <div class="form-group">
                        <label class="form-label">Total Hours</label>
                        <div style="position: relative;">
                            <i class="ri-timer-2-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                            <input type="number" step="0.1" name="hours" id="hours" class="form-control" placeholder="0.0" oninput="calculateOT()" required style="padding-left: 2.5rem; font-weight: 600;">
                        </div>
                    </div>
                </div>

                 <div class="form-group">
                    <label class="form-label">Description / Work Details</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the tasks completed during this overtime period..."></textarea>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px dashed #e2e8f0; padding: 1.5rem; border-radius: 12px;">
                <div style="display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="background: #e0f2fe; color: #0369a1; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="ri-information-line"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 0.9rem; font-weight: 600; color: #0c4a6e; margin-bottom: 0.25rem;">Policy Reminder</h4>
                        <p style="font-size: 0.85rem; color: #334155; line-height: 1.5;">Overtime should be authorized by a supervisor before logging. Standard rates apply unless special multipliers are approved for holiday work.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Calculation & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
             <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.25rem;">Calculation Settings</h3>
                

                
                 <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1.25rem; border-radius: 8px; text-align: center;">
                     <div style="font-size: 0.8rem; color: #166534; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Total OT Amount</div>
                     <div id="estimated_amount" style="font-size: 1.75rem; font-weight: 800; color: #15803d;">₹ 0.00</div>
                     <div id="estimated_note" style="font-size: 0.75rem; color: #166534; margin-top: 0.5rem;">Amount = Salary × OT rate (%)</div>
                 </div>

            </div>

             <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px;">
                 <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1rem; margin-bottom: 0.75rem; border-radius: 8px;">
                    <i class="ri-check-line" style="margin-right: 0.5rem;"></i> Save OT Entry
                </button>
                 <button type="button" onclick="history.back()" class="btn" style="width: 100%; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; padding: 0.75rem; border-radius: 8px;">
                    Cancel
                </button>
            </div>

            <div style="text-align: center;">
                <p style="font-size: 0.8rem; color: #94a3b8;">Employee: <?= htmlspecialchars($employee['role_name'] ?? 'Staff') ?> | Cycle: <?= htmlspecialchars($employee['pay_cycle']) ?></p>
            </div>

        </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
    }
    .form-control, .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: all 0.2s;
        font-family: inherit;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    .btn-primary {
        background: #6366f1;
        color: white;
        border: none;
        font-weight: 600;
    }
    .btn-primary:hover {
        background: #4f46e5;
    }
</style>

<script>
function calculateOT() {
    const salary = <?= floatval($employee['base_salary']) ?>;
    const rate = <?= floatval($otPercentage ?? 0) ?>;
    const amount = ((salary * rate) / 100).toFixed(2);
    const display = isFinite(amount) ? `₹ ${amount}` : '₹ 0.00';
    document.getElementById('estimated_amount').innerText = display;
}

    // Initialize calculation
    window.onload = calculateOT;
</script>

<?php include 'includes/footer.php'; ?>
