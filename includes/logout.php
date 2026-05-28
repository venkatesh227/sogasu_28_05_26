<?php

session_start();
require_once 'db.php';

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'employee'
) {

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            is_logged_in = 0,
            session_token = NULL,
            device_token = NULL
        WHERE id = ?
    ");

    $stmt->execute([$_SESSION['user_id']]);
}

session_destroy();

header("Location: ../index.php");
exit();