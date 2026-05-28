<?php
ob_start();
session_start();
include '../includes/db.php';

// Handle Add Rate Range
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_rate') {
        $from = $_POST['from_date'];
        $to = $_POST['to_date'];
        $rate = $_POST['hourly_rate'];
        
        $stmt = $pdo->prepare("INSERT INTO ot_rate_settings (from_date, to_date, hourly_rate) VALUES (?, ?, ?)");
        $stmt->execute([$from, $to, $rate]);
        header("Location: global-settings.php?success=1");
        exit;
    }
    
    if ($_POST['action'] === 'delete_rate') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ot_rate_settings WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: global-settings.php?deleted=1");
        exit;
    }
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
            <p class="text-muted" style="margin: 0;">Define hourly rates for specific date ranges.</p>
        </div>

        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
            <!-- Form -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem;">Add New Rate Period</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_rate">
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">From Date</label>
                        <input type="date" name="from_date" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">To Date</label>
                        <input type="date" name="to_date" required style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Hourly Rate (₹)</label>
                        <input type="number" name="hourly_rate" step="0.01" required placeholder="e.g. 150" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700;">
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
                                            <div style="font-size: 1.1rem; font-weight: 800; color: #059669;">₹<?= number_format($r['hourly_rate'], 2) ?></div>
                                            <div style="font-size: 0.7rem; color: #64748b;">per hour</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem; text-align: right;">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this rate period?')">
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

<?php include 'includes/footer.php'; ?>
