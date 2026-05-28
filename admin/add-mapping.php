<?php
include '../includes/db.php';

$key_id = $_POST['key_id'];
$sub_cat = $_POST['sub_cat'];
$cat = $_POST['cat'];

date_default_timezone_set('Asia/Kolkata');
$created_at = date("Y-m-d H:i:s");

// duplicate check
$stmt = $pdo->prepare("SELECT id FROM measurement_mapping WHERE key_id=? AND sub_category_id=?");
$stmt->execute([$key_id, $sub_cat]);

if($stmt->rowCount() == 0){

    $stmt = $pdo->prepare("INSERT INTO measurement_mapping 
        (category_id, sub_category_id, key_id, created_at)
        VALUES (?, ?, ?, ?)");

    $stmt->execute([$cat, $sub_cat, $key_id, $created_at]);
}

echo "success";