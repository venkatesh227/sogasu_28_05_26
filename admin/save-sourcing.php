<?php
include '../includes/db.php';
$conn = mysqli_connect($host, $user, $pass, $dbname);

if(!$conn){
    die("Connection Failed");
}

/* =========================
   GET FORM VALUES
========================= */

$id = isset($_POST['id']) ? $_POST['id'] : '';

$customer_name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? '');
$product_name = mysqli_real_escape_string($conn, $_POST['product_name'] ?? '');
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Pending');

// Handle arrays carefully
$source_type = '';
if (isset($_POST['source_type']) && is_array($_POST['source_type'])) {
    $source_type = implode(', ', array_map(function($val) use ($conn) { return mysqli_real_escape_string($conn, $val); }, $_POST['source_type']));
} elseif (isset($_POST['source_type'])) {
    $source_type = mysqli_real_escape_string($conn, $_POST['source_type']);
}

$quantity = '';
if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
    $quantity = implode(', ', array_map(function($val) use ($conn) { return mysqli_real_escape_string($conn, $val); }, $_POST['quantity']));
} elseif (isset($_POST['quantity'])) {
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
}

/* =========================
   TOTAL AMOUNT ARRAY
========================= */

$totalAmounts = isset($_POST['total_amount']) ? $_POST['total_amount'] : [];

$total_amounts = '';
$total_amount = 0;

if (is_array($totalAmounts) && !empty($totalAmounts)) {
    $total_amounts = implode(',', array_map(function($val) use ($conn) { return mysqli_real_escape_string($conn, $val); }, $totalAmounts));
    foreach($totalAmounts as $amt){
        $total_amount += (float)$amt;
    }
} elseif (!is_array($totalAmounts) && $totalAmounts !== null) {
    $total_amounts = mysqli_real_escape_string($conn, $totalAmounts);
    $total_amount = (float)$totalAmounts;
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
    $res = mysqli_query($conn, "SELECT reference_image, attachment_file FROM sourcing WHERE id = '$id'");
    if ($row = mysqli_fetch_assoc($res)) {
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

$reference_image = mysqli_real_escape_string($conn, $reference_image);
$attachment_file = mysqli_real_escape_string($conn, $attachment_file);

/* =========================
   UPDATE
========================= */

if($id != ''){
    $message = '';
    $updateQuery = "
        UPDATE sourcing
        SET
            customer_name = '$customer_name',
            product_name = '$product_name',
            source_type = '$source_type',
            quantity = '$quantity',
            total_amounts = '$total_amounts',
            total_amount = '$total_amount',
            status = '$status',
            reference_image = '$reference_image',
            attachment_file = '$attachment_file'
        WHERE id = '$id'
    ";

    mysqli_query($conn, $updateQuery);
    $message = "updated";
}

/* =========================
   INSERT
========================= */
else{
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
        '$customer_name',
        '$product_name',
        '$source_type',
        '$quantity',
        '$total_amounts',
        '$total_amount',
        '$status',
        '$reference_image',
        '$attachment_file',
        NOW()
      )
    ";

    mysqli_query($conn, $insertQuery);
    $message = "created";
}

/* =========================
   REDIRECT
========================= */

header("Location: sourcing.php?success=".$message);
exit;

?>