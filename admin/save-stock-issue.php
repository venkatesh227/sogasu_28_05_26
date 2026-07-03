<?php

include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    /* =========================
       INVENTORY ITEM ID
       ========================= */

    $itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    if ($itemId <= 0) {
        die("Invalid inventory item ID.");
    }

    $inventoryQuery = $pdo->prepare("
        SELECT id, quantity
        FROM inventory
        WHERE id = ? AND is_deleted = 0
    ");

    $inventoryQuery->execute([$itemId]);

    $inventoryItem = $inventoryQuery->fetch(PDO::FETCH_ASSOC);

    if (!$inventoryItem) {
        die("Inventory Item Not Found");
    }

    $procurement_id = $inventoryItem['id'];

    /* =========================
       ORDER ID (OPTIONAL)
       ========================= */

    $order_id = !empty($_POST['order_id']) ? intval($_POST['order_id']) : null;
    $order_type = !empty($_POST['order_type']) ? $_POST['order_type'] : null;

    /* =========================
       EMPLOYEE ID
       ========================= */

    $employee_id = $_POST['issued_to'];

    /* =========================
       QUANTITY
       ========================= */

    $quantity_issued = intval($_POST['quantity']);
    if ($quantity_issued <= 0) {
        die("Invalid quantity specified.");
    }
    if ($inventoryItem['quantity'] < $quantity_issued) {
        die("Insufficient stock available. Only " . $inventoryItem['quantity'] . " units are left.");
    }

    /* =========================
       ISSUE DATE
       ========================= */

    $issue_date = $_POST['issue_date'];

    /* =========================
       NOTES
       ========================= */

    $notes = "Stock Issued";

    /* =========================
       INSERT STOCK ISSUE (TRANSACTION)
       ========================= */

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO stock_issuance
            (
                procurement_id,
                order_id,
                order_type,
                employee_id,
                quantity_issued,
                issue_date,
                notes
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $procurement_id,
            $order_id,
            $order_type,
            $employee_id,
            $quantity_issued,
            $issue_date,
            $notes
        ]);

        $updateStmt = $pdo->prepare("
            UPDATE inventory
            SET quantity = quantity - ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $quantity_issued,
            $procurement_id
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Database Error: Failed to issue stock. " . $e->getMessage());
    }

    /* =========================
       REDIRECT
       ========================= */
    header("Location: procurement.php?success=stock_issued");
    exit();

}
?>