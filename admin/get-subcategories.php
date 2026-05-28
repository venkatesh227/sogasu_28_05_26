<?php
include '../includes/db.php';

$cat_id = $_GET['cat_id'];

$stmt = $pdo->prepare("SELECT id, name FROM sub_categories WHERE category_id=? AND status='active'");
$stmt->execute([$cat_id]);

echo '<option value="">Select</option>';

while ($row = $stmt->fetch()) {
    echo "<option value='{$row['id']}'>{$row['name']}</option>";
}
?>