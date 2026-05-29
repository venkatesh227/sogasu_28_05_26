<?php
ob_start();
session_start();
include '../includes/db.php';
$errors = [];

$from_date = '';
$to_date = '';
$hourly_rate = '';
// Handle Add Rate Range
if (isset($_POST['action']) && $_POST['action'] === 'add_rate') {
    $from_date = trim($_POST['from_date'] ?? '');
    $to_date = trim($_POST['to_date'] ?? '');
$ot_percentage = trim($_POST['ot_percentage'] ?? '');
    if (empty($from_date)) {
        $errors['from_date'] = 'From Date is required';
    }

    if (empty($to_date)) {
        $errors['to_date'] = 'To Date is required';
    }

    if (empty($ot_percentage)) {
    $errors['ot_percentage'] = 'OT Percentage is required';
}

    if (empty($errors)) {

        $stmt = $pdo->prepare("INSERT INTO ot_rate_settings
(from_date, to_date, ot_percentage)
VALUES (?, ?, ?)");
$stmt->execute([
    $from_date,
    $to_date,
    $ot_percentage
]);
        header("Location: global-settings.php?success=1");
        exit;
    }
}
    
if (isset($_POST['action']) && $_POST['action'] === 'delete_rate') {
            $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ot_rate_settings WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: global-settings.php?deleted=1");
        exit;
    }


$rates = $pdo->query("SELECT * FROM ot_rate_settings ORDER BY from_date DESC")->fetchAll();

$pageTitle = "OT Settings - Sogasu";
$activePage = "global-settings";
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">OT Settings</h2>
            <p class="text-muted" style="margin: 0;">Define overtime percentage rates for specific date ranges. Payroll calculates OT as a percentage of the employee's salary for the selected period.</p>
        </div>

        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
            <!-- Form -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem;">Add New Rate Period</h3>
<form method="POST" novalidate>
                        <input type="hidden" name="action" value="add_rate">
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">From Date</label>
                        <input type="date" name="from_date"  style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <?php if(isset($errors['from_date'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['from_date']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">To Date</label>
                        <input type="date" name="to_date" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <?php if(isset($errors['to_date'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['to_date']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">OT Percentage (%)</label>
<input
    type="number"
    name="ot_percentage"
    step="0.01"
    placeholder="e.g. 50%">
               <?php if(isset($errors['ot_percentage'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['ot_percentage']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700;">Apply Rate Range</button>
                </form>
            </div>

            <!-- List -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0;">Applied Date Ranges</h3>
                </div>
                <div style="padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <th style="padding: 1rem 1.5rem;">Period</th>
                                <th style="padding: 1rem 1.5rem;">Rate</th>
                                <th style="padding: 1rem 1.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rates)): ?>
                                <tr>
                                    <td colspan="3" style="padding: 2rem; text-align: center; color: #94a3b8;">No custom rates defined yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rates as $r): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?= date('d M', strtotime($r['from_date'])) ?> - <?= date('d M, Y', strtotime($r['to_date'])) ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #94a3b8;">Active for these dates</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 1.1rem; font-weight: 800; color: #059669;"><?= number_format($r['ot_percentage'], 2) ?>%</div>
                                            <div style="font-size: 0.7rem; color: #64748b;">salary percentage</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem; text-align: right;">
<form method="POST" style="display: inline;" class="delete-form">
                                                    <input type="hidden" name="action" value="delete_rate">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;" title="Delete Range">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'Rate Added', text: 'New OT rate period successfully applied.', timer: 1500, showConfirmButton: false });</script>
<?php elseif (isset($_GET['deleted'])): ?>
<script>Swal.fire({ icon: 'success', title: 'Deleted', text: 'Rate period removed.', timer: 1500, showConfirmButton: false });</script>
<?php endif; ?>
<script>
document.querySelectorAll('.delete-form').forEach(form => {

    form.addEventListener('submit', function(e) {

        e.preventDefault();

        Swal.fire({
            title: 'Are you sure?',
            text: 'This OT rate period will be deleted permanently!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {

            if(result.isConfirmed){
                form.submit();
            }

        });

    });

});
</script>
<?php include 'includes/footer.php'; ?>
