<?php
include '../includes/db.php';

$id = $_POST['id'] ?? '';

if($id){
    
    $stmt = $pdo->prepare("DELETE FROM measurement_mapping WHERE id=?");
    $stmt->execute([$id]);

    echo "deleted";
}