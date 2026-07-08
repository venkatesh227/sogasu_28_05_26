<?php
include '../includes/db.php';

/* =========================
   GET FORM VALUES
========================= */

$issue_type = $_POST['issue_type'] ?? '';
$ticket_name = $_POST['ticket_name'] ?? '';
$description = $_POST['description'] ?? '';
$priority = $_POST['priority'] ?? '';
$remediation_cost = $_POST['remediation_cost'] ?? 0;
$status = $_POST['status'] ?? 'Open';

/* =========================
   AUTO TICKET NUMBER
========================= */

$ticket_no = 'TIC-' . rand(1000, 9999);

/* =========================
   INSERT QUERY
========================= */

$stmt = $pdo->prepare("
    INSERT INTO support_tickets(
        ticket_no,
        ticket_name,
        issue_type,
        priority,
        status,
        description,
        remediation_cost,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([$ticket_no, $ticket_name, $issue_type, $priority, $status, $description, $remediation_cost]);

/* =========================
   REDIRECT
========================= */

header("Location:support.php?success=created");

exit;
