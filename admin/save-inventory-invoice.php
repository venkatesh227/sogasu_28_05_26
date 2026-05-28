<?php
session_start();
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: inventory.php");
    exit;
}

// 1. Gather & Trim Invoice Header Fields
$invoice_no = trim($_POST['invoice_no'] ?? '');
$invoice_date = trim($_POST['invoice_date'] ?? '');
$supplier_name = trim($_POST['supplier_name'] ?? '');
$contact = trim($_POST['contact'] ?? '');

$errors = [];

// Header Validations
if ($invoice_no === '') {
    $errors[] = "Invoice number is required.";
}
if ($invoice_date === '') {
    $errors[] = "Invoice date is required.";
}
if ($supplier_name === '') {
    $errors[] = "Supplier name is required.";
}
if ($supplier_name !== '' && !preg_match("/^[A-Za-z0-9 ]+$/", $supplier_name)) {
    $errors[] = "Supplier name can only contain letters, numbers, and spaces.";
}
if ($contact !== '' && !preg_match("/^[0-9]{10}$/", $contact)) {
    $errors[] = "Supplier contact must be a 10-digit number.";
}

// 2. Handle Invoice Document Upload
$invoice_file_name = null;
if (!empty($_FILES['invoice_file']['name'])) {
    $file = $_FILES['invoice_file'];
    
    // Size check: 5MB limit
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = "Invoice document must be less than 5MB.";
    }
    
    // Extension verification: PDFs and common images are standard for supplier invoices
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Invoice document must be a PDF, JPG, JPEG, or PNG.";
    }
    
    if (empty($errors)) {
        $invoice_file_name = time() . "_" . basename($file['name']);
        $target_dir = "../uploads/invoices/";
        
        if (!move_uploaded_file($file['tmp_name'], $target_dir . $invoice_file_name)) {
            $errors[] = "Failed to upload invoice document copy.";
        }
    }
}

// 3. Process Multi-Row Item Array
$item_names = $_POST['item_name'] ?? [];
$skus = $_POST['sku'] ?? [];
$categories = $_POST['category'] ?? [];
$units = $_POST['unit'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$costs = $_POST['cost'] ?? [];
$low_stocks = $_POST['low_stock'] ?? [];

$items_count = count($item_names);
if ($items_count === 0) {
    $errors[] = "You must enter at least one inventory item.";
}

// Row-level validations
for ($i = 0; $i < $items_count; $i++) {
    $name = trim($item_names[$i] ?? '');
    $cat = trim($categories[$i] ?? '');
    $unit = trim($units[$i] ?? '');
    $qty = $quantities[$i] ?? '';
    $cost = $costs[$i] ?? '';
    $low = $low_stocks[$i] ?? '';
    
    $row_num = $i + 1;
    
    if ($name === '') {
        $errors[] = "Row #{$row_num}: Item name is required.";
    } elseif (!preg_match("/^[A-Za-z ]+$/", $name)) {
        $errors[] = "Row #{$row_num}: Item name ('{$name}') is invalid (letters only).";
    }
    
    if ($cat === '') {
        $errors[] = "Row #{$row_num}: Category selection is required.";
    }
    if ($unit === '') {
        $errors[] = "Row #{$row_num}: Unit selection is required.";
    }
    
    if ($qty === '' || !is_numeric($qty) || $qty < 0) {
        $errors[] = "Row #{$row_num}: Quantity must be a positive number.";
    }
    if ($cost === '' || !is_numeric($cost) || $cost < 0) {
        $errors[] = "Row #{$row_num}: Cost per unit must be a positive price.";
    }
    if ($low === '' || !is_numeric($low) || $low < 0) {
        $errors[] = "Row #{$row_num}: Min. stock alert level must be a positive integer.";
    }
}

// 4. Save and Redirect
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: add-inventory-invoice.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO inventory 
    (
        item_name,
        sku,
        category,
        description,
        unit,
        cost,
        quantity,
        low_stock_alert,
        status,
        supplier_name,
        supplier_contact,
        invoice_no,
        invoice_date,
        invoice_file,
        created_at,
        is_deleted
    )
    VALUES
    (
        ?, ?, ?, '', ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 0
    )");
    
    $created_at = date("Y-m-d H:i:s");
    
    for ($i = 0; $i < $items_count; $i++) {
        $name = trim($item_names[$i]);
        $sku = trim($skus[$i] ?? '');
        $cat = trim($categories[$i]);
        $unit = trim($units[$i]);
        $qty = floatval($quantities[$i]);
        $cost = floatval($costs[$i]);
        $low = intval($low_stocks[$i]);
        
        // Dynamic SKU auto-generation if omitted by user
        if ($sku === '') {
            $cat_prefix = strtoupper(substr($cat, 0, 3));
            if ($cat === 'access') $cat_prefix = 'ACC';
            if ($cat_prefix === '') $cat_prefix = 'INV';
            
            $clean_name = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
            $name_prefix = substr($clean_name, 0, 3);
            if (strlen($name_prefix) < 3) {
                $name_prefix = str_pad($name_prefix, 3, 'X');
            }
            
            $suffix = rand(1000, 9999);
            $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
            
            // Check database for collisions and iterate suffix
            $chk = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE sku = ? AND is_deleted = 0");
            while (true) {
                $chk->execute([$sku]);
                if ($chk->fetchColumn() == 0) {
                    break;
                }
                $suffix = rand(1000, 9999);
                $sku = $cat_prefix . '-' . $name_prefix . '-' . $suffix;
            }
        }
        
        $stmt->execute([
            $name,
            $sku,
            $cat,
            $unit,
            $cost,
            $qty,
            $low,
            $supplier_name,
            $contact !== '' ? $contact : null,
            $invoice_no,
            $invoice_date,
            $invoice_file_name,
            $created_at
        ]);
    }
    
    $pdo->commit();
    $_SESSION['success'] = "Successfully registered " . $items_count . " stock items from Invoice " . htmlspecialchars($invoice_no) . "!";
    header("Location: inventory.php");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Database insertion error: " . $e->getMessage();
    header("Location: add-inventory-invoice.php");
    exit;
}
