<?php

include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO procurement
        (
            material_name,
            quantity,
            unit,
            expected_cost,
            vendor_name,
            status,
            expected_date
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([

        $_POST['material_name'],
        $_POST['quantity'],
        $_POST['unit'],
        $_POST['expected_cost'],
        $_POST['vendor_name'],
        $_POST['status'],
        $_POST['expected_date']

    ]);

  header("Location: procurement.php?success=stock_added");
exit();
}
?>