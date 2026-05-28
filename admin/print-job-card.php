<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    die("Order ID required");
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.first_name, c.last_name, c.phone,
           fm.member_name as family_member, fm.relationship,
           cat.category_name as category_name, 
           sc.name as sub_category_name,
           e.first_name as supervisor_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN customer_family_members fm ON o.family_member_id = fm.id
    LEFT JOIN categories cat ON o.category_id = cat.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    LEFT JOIN employees e ON o.supervisor_id = e.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found");
}

// Fetch Measurements
$stmt_m = $pdo->prepare("SELECT * FROM order_measurements WHERE order_id = ?");
$stmt_m->execute([$order_id]);
$measurements = $stmt_m->fetchAll();
 
// Fetch Additional Services
$stmt_s = $pdo->prepare("
    SELECT os.service_price, s.service_name 
    FROM order_services os
    JOIN services s ON os.service_id = s.id
    WHERE os.order_id = ?
");
$stmt_s->execute([$order_id]);
$order_services = $stmt_s->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Card - <?= htmlspecialchars($order['order_code']) ?></title>
    <style>
        body { 
            font-family: 'Courier New', Courier, monospace; 
            width: 80mm; 
            margin: 0; 
            padding: 5mm; 
            font-size: 12px;
            color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .border-top { border-top: 1px dashed #000; margin-top: 5px; padding-top: 5px; }
        .separator { border-top: 1px dashed #000; margin: 10px 0; }
        
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 10px; }
        
        .info-row { display: flex; justify-content: space-between; margin: 2px 0; }
        .label { font-weight: bold; }
        
        .measurements { margin-top: 10px; }
        .m-table { width: 100%; border-collapse: collapse; }
        .m-table td { padding: 3px 0; border-bottom: 1px dotted #ccc; }
        .m-val { text-align: right; font-weight: bold; font-size: 14px; }
        
        .notes { margin-top: 10px; font-style: italic; font-size: 11px; }
        
        @media print {
            .no-print { display: none; }
            body { width: 80mm; padding: 0; }
        }
        .btn-print {
            background: #000;
            color: #fff;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            margin-bottom: 20px;
            width: 100%;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">PRINT TICKET</button>
    </div>

    <div class="header center">
        <h2>SOGASU</h2>
        <p>Boutique & Stitching Studio</p>
        <div class="separator"></div>
        <p class="bold" style="font-size: 14px;">JOB CARD #<?= htmlspecialchars($order['order_code']) ?></p>
        <p>Due: <?= date('d-M-Y', strtotime($order['due_date'])) ?></p>
    </div>

    <div class="separator"></div>

    <div class="info-row">
        <span class="label">Customer:</span>
        <span><?= htmlspecialchars($order['first_name']) ?></span>
    </div>
    <?php if ($order['family_member']): ?>
    <div class="info-row">
        <span class="label">For:</span>
        <span><?= htmlspecialchars($order['family_member']) ?></span>
    </div>
    <?php endif; ?>
    <div class="info-row">
        <span class="label">Garment:</span>
        <span><?= htmlspecialchars($order['sub_category_name']) ?></span>
    </div>
    <div class="info-row">
        <span class="label">Unit:</span>
        <span><?= $order['measurement_unit'] ?></span>
    </div>

    <div class="separator"></div>
    <div class="center bold">MEASUREMENTS</div>
    
    <table class="m-table">
        <?php foreach ($measurements as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['key_name']) ?></td>
            <td class="m-val"><?= htmlspecialchars($m['measurement_value']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if (!empty($order_services)): ?>
    <div class="separator"></div>
    <div class="center bold">SERVICES</div>
    <table class="m-table">
        <?php foreach ($order_services as $srv): ?>
        <tr>
            <td><?= htmlspecialchars($srv['service_name']) ?></td>
            <td class="m-val" style="font-size: 11px;">₹ <?= number_format($srv['service_price']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($order['notes'])): ?>
    <div class="separator"></div>
    <div class="bold">NOTES:</div>
    <div class="notes">
        <?= nl2br(htmlspecialchars($order['notes'])) ?>
    </div>
    <?php endif; ?>

    <div class="separator"></div>
    <div class="center" style="font-size: 10px;">
        Generated: <?= date('d/m/y H:i') ?><br>
        *** Thank You ***
    </div>

</body>
</html>
