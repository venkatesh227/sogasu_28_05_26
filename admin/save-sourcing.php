<?php
include '../includes/db.php';
$conn = $pdo;

/* =========================
   GET FORM VALUES
========================= */

$id = isset($_POST['id']) ? $_POST['id'] : '';

$customer_name = trim($_POST['customer_name'] ?? '');
$product_name = trim($_POST['product_name'] ?? '');
$status = trim($_POST['status'] ?? 'Pending');

// Handle arrays carefully
$source_type = '';
if (isset($_POST['source_type']) && is_array($_POST['source_type'])) {
    $source_type = implode(', ', array_map('trim', $_POST['source_type']));
} elseif (isset($_POST['source_type'])) {
    $source_type = trim($_POST['source_type']);
}

$quantity = '';
if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
    $quantity = implode(', ', array_map('trim', $_POST['quantity']));
} elseif (isset($_POST['quantity'])) {
    $quantity = trim($_POST['quantity']);
}

/* =========================
   TOTAL AMOUNT ARRAY
========================= */

$totalAmounts = isset($_POST['total_amount']) ? $_POST['total_amount'] : [];

$total_amounts = '';
$total_amount = 0;

if (is_array($totalAmounts) && !empty($totalAmounts)) {
    $total_amounts = implode(',', array_map('trim', $totalAmounts));
    foreach ($totalAmounts as $amt) {
        $total_amount += (float) $amt;
    }
} elseif (!is_array($totalAmounts) && $totalAmounts !== null) {
    $total_amounts = trim($totalAmounts);
    $total_amount = (float) $totalAmounts;
}

/* =========================
   FILE UPLOADS
========================= */
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Fetch existing files if it's an update
$existing_reference = '';
$existing_attachment = '';
if ($id != '') {

    $stmt = $conn->prepare("
        SELECT reference_image, attachment_file
        FROM sourcing
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_reference = $row['reference_image'];
        $existing_attachment = $row['attachment_file'];
    }
}

// Reference Image
$reference_image = $existing_reference;
if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_' . basename($_FILES['reference_image']['name']);
    if (move_uploaded_file($_FILES['reference_image']['tmp_name'], $uploadDir . $filename)) {
        $reference_image = 'uploads/' . $filename;
    }
}

// Attachment File
$attachment_file = $existing_attachment;
if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_' . basename($_FILES['attachment_file']['name']);
    if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $uploadDir . $filename)) {
        $attachment_file = 'uploads/' . $filename;
    }
}

/* =========================
   UPDATE
========================= */

if ($id != '') {
    $message = '';
    $updateQuery = "
UPDATE sourcing
SET
    customer_name=?,
    product_name=?,
    source_type=?,
    quantity=?,
    total_amounts=?,
    total_amount=?,
    status=?,
    reference_image=?,
    attachment_file=?
WHERE id=?
";

    $stmt = $conn->prepare($updateQuery);

    $stmt->execute([
        $customer_name,
        $product_name,
        $source_type,
        $quantity,
        $total_amounts,
        $total_amount,
        $status,
        $reference_image,
        $attachment_file,
        $id
    ]);

    $message = "updated";
}

/* =========================
   INSERT
========================= */ else {
    $insertQuery = "
INSERT INTO sourcing(
    customer_name,
    product_name,
    source_type,
    quantity,
    total_amounts,
    total_amount,
    status,
    reference_image,
    attachment_file,
    created_at
)
VALUES(
    ?,?,?,?,?,?,?,?,?,NOW()
)
";

    $stmt = $conn->prepare($insertQuery);

    $stmt->execute([
        $customer_name,
        $product_name,
        $source_type,
        $quantity,
        $total_amounts,
        $total_amount,
        $status,
        $reference_image,
        $attachment_file
    ]);

    $message = "created";

}

/* =========================
   REDIRECT
========================= */

header("Location: sourcing.php?success=" . $message);
exit;

?>