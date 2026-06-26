<?php
session_start();
require_once '../includes/db.php';

// Handle approve/reject actions BEFORE any output (so header redirects work)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $requestId = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM shift_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        if ($_GET['action'] === 'approve') {
            // Upsert roster
            $up = $pdo->prepare("INSERT INTO shift_roster (employee_id, shift_type_id, roster_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE shift_type_id = VALUES(shift_type_id)");
            $up->execute([
                $request['employee_id'],
                $request['requested_shift_id'],
                $request['request_date']
            ]);

            // Update request status
            $u2 = $pdo->prepare("UPDATE shift_requests SET status = 'Approved' WHERE id = ?");
            $u2->execute([$requestId]);

            // Fetch shift details for notification
            $st = $pdo->prepare("SELECT name, start_time, end_time FROM shift_types WHERE id = ?");
            $st->execute([$request['requested_shift_id']]);
            $stdata = $st->fetch(PDO::FETCH_ASSOC);

            $title = 'Shift Request Approved';
            $shiftLabel = $stdata ? $stdata['name'] . ' (' . date('H:i', strtotime($stdata['start_time'])) . '-' . date('H:i', strtotime($stdata['end_time'])) . ')' : 'Requested Shift';
            $message = "Your shift request for " . date('d M Y', strtotime($request['request_date'])) . " to {$shiftLabel} has been approved.";

            $ins = $pdo->prepare("INSERT INTO notifications (employee_id, title, message) VALUES (?, ?, ?)");
            $ins->execute([$request['employee_id'], $title, $message]);
        }

        if ($_GET['action'] === 'reject') {
            $u2 = $pdo->prepare("UPDATE shift_requests SET status = 'Rejected' WHERE id = ?");
            $u2->execute([$requestId]);

            $title = 'Shift Request Rejected';
            $message = "Your shift request for " . date('d M Y', strtotime($request['request_date'])) . " has been rejected.";

            $ins = $pdo->prepare("INSERT INTO notifications (employee_id, title, message) VALUES (?, ?, ?)");
            $ins->execute([$request['employee_id'], $title, $message]);
        }
    }

    header('Location: shift-requests.php');
    exit;
}

// Admin page header
$pageTitle = 'Shift Requests - Sogasu';
$activePage = 'shift-roster';
include 'includes/header.php';

// Fetch pending requests
$stmt = $pdo->prepare(
    "SELECT
        sr.*,
        e.first_name,
        e.last_name,
        e.job_role,
        st.name AS shift_name,
        st.start_time,
        st.end_time
    FROM shift_requests sr
    LEFT JOIN employees e ON e.id = sr.employee_id
    LEFT JOIN shift_types st ON st.id = sr.requested_shift_id
    WHERE sr.status = 'Pending'
    ORDER BY sr.created_at DESC"
);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.card{
    background:#fff;
    border-radius:12px;
    padding:20px;
    margin-bottom:15px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.top{
    display:flex;
    justify-content:space-between;
}

.name{
    font-size:18px;
    font-weight:700;
}

.role{
    color:#64748b;
    font-size:13px;
}

.shift{
    color:#10b981;
    font-weight:700;
}

.btn-row{
    display:flex;
    gap:10px;
    margin-top:15px;
}

.approve{
    flex:1;
    text-align:center;
    background:#10b981;
    color:white;
    padding:12px;
    border-radius:8px;
    text-decoration:none;
}

.reject{
    flex:1;
    text-align:center;
    background:#ef4444;
    color:white;
    padding:12px;
    border-radius:8px;
    text-decoration:none;
}
.back-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:12px 20px;
    background:#fff;
    color:#334155;
    border:1px solid #e5e7eb;
    border-radius:14px;
    text-decoration:none;
    font-size:15px;
    font-weight:600;
    box-shadow:0 3px 10px rgba(0,0,0,.08);
    transition:.3s;
}

.back-btn i{
    font-size:16px;
}

.back-btn:hover{
    background:#f8fafc;
    color:#2563eb;
    border-color:#2563eb;
    text-decoration:none;
}
</style>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

<div style="padding: 1.25rem;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="font-size:1.25rem;font-weight:700;color:#1e293b;margin:0;">
            Shift Requests
        </h2>

<a href="shift-roster.php" class="back-btn">
    <i class="fas fa-arrow-left"></i>
    ← Back to Shift Roster
</a>
    </div>
        <?php if (empty($requests)): ?>
            <div class="card">No Pending Requests</div>
        <?php endif; ?>

        <?php foreach ($requests as $row): ?>
            <div class="card">
                <div class="top">
                    <div>
                        <div class="name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                        <div class="role"><?= htmlspecialchars($row['job_role']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="shift"><?= htmlspecialchars($row['shift_name']) ?></div>
                        <small><?= date('d M Y', strtotime($row['request_date'])) ?></small>
                    </div>
                </div>

                <hr>

                <p>
                    <b>Shift Timing:</b><br>
                    <?= htmlspecialchars($row['start_time']) ?> - <?= htmlspecialchars($row['end_time']) ?>
                </p>

                <p><b>Reason:</b><br><?= htmlspecialchars($row['reason']) ?></p>

                <div class="btn-row">
                    <a class="approve" href="shift-requests.php?action=approve&id=<?= $row['id'] ?>">Approve</a>
                    <a class="reject" href="shift-requests.php?action=reject&id=<?= $row['id'] ?>">Reject</a>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
