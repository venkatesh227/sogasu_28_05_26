<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "sogasu"
);

if(!$conn){
    die("Connection Failed");
}

/* =========================
   GET FORM VALUES
========================= */

$issue_type = $_POST['issue_type'];
$ticket_name = $_POST['ticket_name'];
$description = $_POST['description'];

$priority = $_POST['priority'];

$remediation_cost = $_POST['remediation_cost'];
$status = $_POST['status'];
/* =========================
   AUTO TICKET NUMBER
========================= */

$ticket_no = 'TIC-' . rand(1000,9999);

/* =========================
   INSERT QUERY
========================= */

$insertQuery = "

INSERT INTO support_tickets(

    ticket_no,
    ticket_name,
    issue_type,
    priority,
    status,
    description,
    remediation_cost,
    created_at

)

VALUES(

    '$ticket_no',
    '$ticket_name',
    '$issue_type',
    '$priority',
    '$status',
    '$description',
    '$remediation_cost',
    NOW()

)

";
mysqli_query($conn, $insertQuery);

/* =========================
   REDIRECT
========================= */

header("Location:support.php?success=created");

exit;

?>