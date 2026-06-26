<?php
session_start();
require_once '../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid Order ID");
}

$stmt = $pdo->prepare("
    SELECT 
        o.*,
        c.first_name,
        c.address,
        COALESCE(u.mobile, c.phone) AS mobile,
        cat.category_name,
        sc.name AS sub_category_name
    FROM outsource_orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN categories cat ON o.category_id = cat.id
    LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
    WHERE o.id = ?
");

$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found");
}

$measureStmt = $pdo->prepare("
    SELECT key_name, measurement_value
    FROM order_measurements
    WHERE order_id = ?
    AND order_type = 'outsource'
");
$measureStmt->execute([$id]);
$measurements = $measureStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Outsource Order</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        *{
            box-sizing:border-box;
            margin:0;
            padding:0;
            font-family:Inter,sans-serif;
        }

        body{
            background:#fdf2f8;
            padding:25px;
        }

        .container{
            max-width:1100px;
            margin:auto;
        }

        .top-bar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;
        }

        .back-btn{
            text-decoration:none;
            background:white;
            padding:12px 18px;
            border-radius:12px;
            color:#111;
            font-weight:600;
        }

        .status{
            background:#fef3c7;
            color:#d97706;
            padding:8px 14px;
            border-radius:30px;
            font-weight:600;
        }

        .card{
            background:white;
            border-radius:20px;
            padding:25px;
            margin-bottom:20px;
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
        }

        .title{
            font-size:22px;
            font-weight:700;
            color:#831843;
        }

        .section-title{
            font-size:18px;
            font-weight:700;
            margin-bottom:20px;
            color:#831843;
        }

        .grid{
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:16px;
        }

        .info-box{
            background:#fff1f2;
            padding:15px;
            border-radius:14px;
        }

        .label{
            font-size:13px;
            color:#666;
            margin-bottom:6px;
        }

        .value{
            font-weight:600;
            color:#111;
        }

        .measure-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:16px;
        }

        .measure-box{
            background:#fdf2f8;
            padding:16px;
            border-radius:14px;
            text-align:center;
        }

        .measure-name{
            color:#666;
            font-size:14px;
        }

        .measure-value{
            margin-top:8px;
            font-size:20px;
            font-weight:700;
            color:#db2777;
        }

        .images{
            display:flex;
            gap:20px;
            flex-wrap:wrap;
        }

        .images img{
            width:250px;
            border-radius:16px;
            box-shadow:0 4px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>
<div class="container">

    <div class="top-bar">
        <a class="back-btn" href="outsource-orders.php">
            <i class="ri-arrow-left-line"></i> Back
        </a>

        <div>
            <div class="title">Order #<?= $order['order_code'] ?></div>
        </div>

        <div class="status"><?= ucfirst($order['order_status']) ?></div>
    </div>

    <div class="card">
        <div class="section-title">Order Summary</div>

        <div class="grid">
            <div class="info-box">
                <div class="label">Garment</div>
                <div class="value"><?= $order['category_name'] ?></div>
            </div>

            <div class="info-box">
                <div class="label">Sub Category</div>
                <div class="value"><?= $order['sub_category_name'] ?></div>
            </div>

            <div class="info-box">
                <div class="label">Fabric</div>
                <div class="value"><?= $order['fabric_details'] ?: 'N/A' ?></div>
            </div>

            <div class="info-box">
                <div class="label">Due Date</div>
                <div class="value"><?= date('d M Y', strtotime($order['due_date'])) ?></div>
            </div>
        </div>

        <div style="margin-top:20px;">
            <div class="label">Notes</div>
            <div class="value"><?= $order['notes'] ?: 'No notes available' ?></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Measurements</div>

        <div class="measure-grid">
            <?php foreach($measurements as $m): ?>
                <div class="measure-box">
                    <div class="measure-name"><?= $m['key_name'] ?></div>
                    <div class="measure-value"><?= $m['measurement_value'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Reference Images</div>

        <div class="images">
            <?php if(!empty($order['material_image'])): ?>
                <img src="../<?= $order['material_image'] ?>">
            <?php endif; ?>

            <?php if(!empty($order['referral_image'])): ?>
                <img src="../<?= $order['referral_image'] ?>">
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>