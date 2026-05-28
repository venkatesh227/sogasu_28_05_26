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
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';
    $method = $_POST['method'] ?? 'Cash';
    
    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO employee_payments (employee_id, payment_date, description, payment_type, amount, status) VALUES (?, ?, ?, 'Advance', ?, 'Paid')");
        $stmt->execute([$id, $payment_date, 'Advance Given (' . $method . ') ' . ($notes ? "- $notes" : ""), $amount]);
        
        $_SESSION['success'] = "Advance of ₹" . number_format($amount, 2) . " recorded for " . htmlspecialchars($employee['first_name']);
    }
    
    header("Location: payroll.php");
    exit;
}

$pageTitle = "Give Advance - Sogasu";
$activePage = "payroll";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">
        
        <!-- Header Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Give Cash Advance</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Recording an advance payment for <strong style="color: var(--primary);"><?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?></strong></p>
            </div>
            <a href="payroll.php" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                <i class="ri-arrow-left-line"></i> Back to Payroll
            </a>
        </div>

        <!-- Main Form & Content -->
        <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            
            <!-- Left Column: Advance Details -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Transaction Details Card -->
                <div class="glass-card" style="padding: 2rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ri-bank-card-line" style="color: var(--primary);"></i> Transaction Details
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Amount to Give</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; font-weight: 700; font-size: 1.1rem;">₹</span>
                                <input type="number" step="1" name="amount" class="premium-input" placeholder="0" required style="padding-left: 2.25rem; font-size: 1.1rem; font-weight: 700; color: var(--text-dark); border-radius: 10px;">
                            </div>
                        </div>
                         <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="premium-input" value="<?php echo date('Y-m-d'); ?>" style="font-weight: 700; border-radius: 10px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Payment Method</label>
                        
                        <div class="method-selector" style="display: flex; gap: 1rem; margin-top: 0.25rem;">
                            <label class="method-option">
                                <input type="radio" name="method" value="Cash" checked style="display: none;">
                                <div class="method-card">
                                    <i class="ri-money-dollar-circle-line"></i>
                                    <span>Cash</span>
                                </div>
                            </label>
                            <label class="method-option">
                                <input type="radio" name="method" value="Bank Transfer" style="display: none;">
                                <div class="method-card">
                                    <i class="ri-bank-line"></i>
                                    <span>Bank Transfer</span>
                                </div>
                            </label>
                            <label class="method-option">
                                <input type="radio" name="method" value="UPI" style="display: none;">
                                <div class="method-card">
                                    <i class="ri-qr-code-line"></i>
                                    <span>UPI</span>
                                </div>
                            </label>
                        </div>
                    </div>

                     <div class="form-group">
                        <label class="form-label">Notes / Reason</label>
                        <textarea name="notes" class="premium-input" rows="3" placeholder="e.g. For emergency medical expense..." style="border-radius: 10px; resize: vertical; min-height: 80px;"></textarea>
                    </div>
                </div>

                <!-- Advance Policy Warning -->
                <div style="background: #fffbeb; border: 1px dashed #f59e0b; padding: 1.25rem; border-radius: 12px;">
                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <div style="background: #fef3c7; color: #d97706; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;">
                            <i class="ri-information-line"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 0.9rem; font-weight: 700; color: #b45309; margin: 0 0 0.25rem 0;">Advance Policy Alert</h4>
                            <p style="font-size: 0.8rem; color: #d97706; line-height: 1.5; margin: 0; font-weight: 500;">This recorded amount will be added to the employee's outstanding advance balance. It will automatically accumulate in calculations and can be easily deducted from their upcoming salary payouts inside the Process Payment screen.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Actions & Profile -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Employee Summary Card -->
                <div class="glass-card" style="padding: 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <div class="premium-avatar" style="width: 64px; height: 64px; font-size: 1.75rem; border-radius: 50%; background: #e0e7ff; color: #4338ca; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-sm);">
                        <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin: 0;"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;"><?= htmlspecialchars($employee['role_name'] ?? 'Staff Member') ?></p>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); background: #f1f5f9; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                        Employee ID: #<?= $employee['id'] ?>
                    </div>
                </div>

                <!-- Confirm Actions Card -->
                <div class="glass-card" style="padding: 1.5rem;">
                     <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                         <i class="ri-checkbox-circle-line" style="color: var(--success);"></i> Confirm Action
                     </h3>
                     
                     <div style="margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; font-weight: 500;">
                        Recording this advance transaction will deduct the cash balance from company reserves and create a corresponding recovery ledger for this employee.
                     </div>

                     <button type="submit" class="btn-premium" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem; margin-bottom: 0.75rem; border-radius: 10px; border: none; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s;">
                        <i class="ri-check-line" style="font-size: 1.1rem;"></i> Confirm & Record
                     </button>
                     <a href="payroll.php" class="btn" style="width: 100%; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; padding: 0.85rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: flex; align-items: center; transition: all 0.2s; box-sizing: border-box; text-align: center;">
                        Cancel
                    </a>
                </div>

            </div>

        </form>
    </div>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .method-option {
        flex: 1;
        cursor: pointer;
    }
    .method-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.85rem;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        transition: all 0.2s;
        font-weight: 600;
        color: var(--text-muted);
        text-align: center;
        box-shadow: var(--shadow-sm);
    }
    .method-card i {
        font-size: 1.35rem;
        color: var(--text-muted);
    }
    .method-option input:checked + .method-card {
        border-color: var(--primary);
        background: rgba(79, 70, 229, 0.08);
        color: var(--primary);
        box-shadow: 0 0 0 1px var(--primary);
    }
    .method-option input:checked + .method-card i {
        color: var(--primary);
    }
    .method-card:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-light);
    }
</style>

<?php include 'includes/footer.php'; ?>
