<?php
session_start();
require_once '../includes/db.php';

if (isset($_GET['action']) && isset($_GET['id'])) {

    $requestId = (int)$_GET['id'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM shift_requests
        WHERE id=?
    ");
    $stmt->execute([$requestId]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {

        if ($_GET['action'] == 'approve') {

          $stmt = $pdo->prepare("
INSERT INTO shift_roster
(employee_id, shift_type_id, roster_date)
VALUES (?, ?, ?)
ON DUPLICATE KEY UPDATE
shift_type_id = VALUES(shift_type_id)
");

$stmt->execute([
    $request['employee_id'],
    $request['requested_shift_id'],
    $request['request_date']
]);

            $stmt = $pdo->prepare("
                UPDATE shift_requests
                SET status='Approved'
                WHERE id=?
            ");

            $stmt->execute([$requestId]);
        }

        if ($_GET['action'] == 'reject') {

            $stmt = $pdo->prepare("
                UPDATE shift_requests
                SET status='Rejected'
                WHERE id=?
            ");

            $stmt->execute([$requestId]);
        }
    }

header("Location: shift-roster.php");
exit;
}

$stmt = $pdo->prepare("
    SELECT
        sr.*,
        e.first_name,
        e.last_name,
        e.job_role,
        st.name AS shift_name,
        st.start_time,
        st.end_time
    FROM shift_requests sr
    LEFT JOIN employees e
        ON e.id = sr.employee_id
    LEFT JOIN shift_types st
        ON st.id = sr.requested_shift_id
    WHERE sr.status='Pending'
    ORDER BY sr.created_at DESC
");
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

</style>
</head>
<body>


<?php if(empty($requests)): ?>

<div class="card">
    No Pending Requests
</div>

<?php endif; ?>

<?php foreach($requests as $row): ?>

<div class="card">

    <div class="top">

        <div>

            <div class="name">
                <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>
            </div>

            <div class="role">
                <?= htmlspecialchars($row['job_role']) ?>
            </div>

        </div>

        <div style="text-align:right;">

            <div class="shift">
                <?= htmlspecialchars($row['shift_name']) ?>
            </div>

            <small>
                <?= date('d M Y', strtotime($row['request_date'])) ?>
            </small>

        </div>

    </div>

    <hr>

    <p>
        <b>Shift Timing:</b><br>
        <?= $row['start_time'] ?> -
        <?= $row['end_time'] ?>
    </p>

    <p>
        <b>Reason:</b><br>
        <?= htmlspecialchars($row['reason']) ?>
    </p>

    <div class="btn-row">

       <a class="approve"
   href="shift-requests.php?action=approve&id=<?= $row['id'] ?>">
   Approve
</a>

<a class="reject"
   href="shift-requests.php?action=reject&id=<?= $row['id'] ?>">
   Reject
</a>

    </div>

</div>

<?php endforeach; ?>

