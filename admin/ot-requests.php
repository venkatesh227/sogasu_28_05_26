<?php
ob_start();
session_start();
include '../includes/db.php';

// Handle Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {

    $ot_id  = (int)$_POST['ot_id'];
    $status = $_POST['status'];

    // Get OT Request Details
    $stmt = $pdo->prepare("SELECT * FROM employee_overtime WHERE id = ?");
    $stmt->execute([$ot_id]);
    $ot = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ot) {

        // Update Status
        $update = $pdo->prepare("UPDATE employee_overtime SET status = ? WHERE id = ?");
        $update->execute([$status, $ot_id]);

        // Notification Title & Message
        if ($status == 'Approved') {

            $title = 'Overtime Request Approved';
            $message = "Your overtime request for "
                . date('d M Y', strtotime($ot['ot_date']))
                . " (" . $ot['hours'] . " Hours)"
                . " has been approved.";

        } else {

            $title = 'Overtime Request Rejected';
            $message = "Your overtime request for "
                . date('d M Y', strtotime($ot['ot_date']))
                . " (" . $ot['hours'] . " Hours)"
                . " has been rejected.";

        }

        // Insert Notification
        $notify = $pdo->prepare("
            INSERT INTO notifications
            (employee_id, title, message)
            VALUES (?, ?, ?)
        ");

        $notify->execute([
            $ot['employee_id'],
            $title,
            $message
        ]);

        header("Location: ot-requests.php?success=1");
        exit;
    }
}

// Fetch Pending
$pending = $pdo->query("
    SELECT ot.*, e.first_name, e.last_name, e.job_role
    FROM employee_overtime ot
    JOIN employees e ON ot.employee_id = e.id
    WHERE ot.status = 'Pending'
    ORDER BY ot.ot_date ASC
")->fetchAll();

// Fetch History
$history = $pdo->query("
    SELECT ot.*, e.first_name, e.last_name
    FROM employee_overtime ot
    JOIN employees e ON ot.employee_id = e.id
    WHERE ot.status != 'Pending'
    ORDER BY ot.created_at DESC
    LIMIT 100
")->fetchAll();

$pageTitle = "Overtime Requests - Sogasu";
$activePage = "ot-requests";
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Overtime (OT) Requests</h2>
            <p class="text-muted" style="margin: 0;">Review and approve extra working hours submitted by employees.</p>
        </div>

        <?php if (empty($pending)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 3rem; text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1rem;"><i class="ri-timer-line"></i></div>
                <h3 style="color: #64748b; margin: 0;">No pending OT requests</h3>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                <?php foreach ($pending as $r): ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <div style="padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($r['job_role']) ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.1rem; font-weight: 800; color: #059669;"><?= $r['hours'] ?> <span style="font-size: 0.75rem; font-weight: 400;">Hrs</span></div>
                                <div style="font-size: 0.7rem; color: #64748b;">₹<?= number_format($r['amount'], 0) ?></div>
                            </div>
                        </div>
                        <div style="padding: 1.25rem;">
                            <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 1rem;">
                                <i class="ri-calendar-line"></i> Date: <strong><?= date('d M, Y', strtotime($r['ot_date'])) ?></strong><br>
                                <i class="ri-chat-3-line"></i> Note: <?= htmlspecialchars($r['description']) ?>
                            </div>
                            <div style="display: flex; gap: 0.75rem;">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="process">
                                    <input type="hidden" name="ot_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <button type="submit" class="btn" style="width: 100%; background: #059669; color: white; border: none; padding: 0.6rem; border-radius: 6px; font-weight: 600;">Approve</button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="process">
                                    <input type="hidden" name="ot_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="Rejected">
                                    <button type="submit" class="btn" style="width: 100%; background: #e11d48; color: white; border: none; padding: 0.6rem; border-radius: 6px; font-weight: 600;">Reject</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- History Table -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">OT History</h3>
            </div>
            <div style="padding: 1.5rem; overflow-x: auto;">
                <table id="otHistoryTable" class="display" style="width: 100%;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.85rem; font-weight: 600;">
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Hours</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem 0;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></div>
                                </td>
                                <td style="font-size: 0.85rem; color: #475569;"><?= date('d M, Y', strtotime($h['ot_date'])) ?></td>
                                <td style="font-weight: 700; color: #1e293b;"><?= $h['hours'] ?></td>
                                <td style="font-weight: 700; color: #059669;">₹<?= number_format($h['amount'], 2) ?></td>
                                <td>
                                    <?php $hColor = $h['status'] === 'Approved' ? '#059669' : '#e11d48'; ?>
                                    <span style="font-size: 0.7rem; color: <?= $hColor ?>; font-weight: 800; text-transform: uppercase;"><?= $h['status'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('otHistoryTable', 'OT History');
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'Processed', text: 'OT request updated successfully.', timer: 1500, showConfirmButton: false });</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
