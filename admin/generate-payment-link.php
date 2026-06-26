<?php

require_once '../includes/db.php';
require_once '../includes/razorpay-config.php';

if (!isset($_GET['order_id'])) {
    die("Invalid order.");
}

$order_id = (int) $_GET['order_id'];
$order_type = $_GET['order_type'] ?? 'orders';

$table = ($order_type === 'outsource_orders')
    ? 'outsource_orders'
    : 'orders';

/*
|--------------------------------------------------------------------------
| FETCH ORDER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM {$table}
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$order_id]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| PAYMENT CALCULATIONS
|--------------------------------------------------------------------------
*/

$total_amount = (float)$order['total_amount'];

$paid_amount = (float)$order['paid_amount'];

$advance_amount = (float)$order['advance_amount'];

$remaining_amount = $total_amount - $paid_amount;

/*
|--------------------------------------------------------------------------
| CHECK FULLY PAID
|--------------------------------------------------------------------------
*/

if ($remaining_amount <= 0) {
    die("Order already fully paid.");
}

/*
|--------------------------------------------------------------------------
| DETERMINE PAYABLE AMOUNT
|--------------------------------------------------------------------------
*/

if ($paid_amount <= 0) {

    if ($advance_amount > 0) {
        $payable_amount = $advance_amount;
    } else {
        $payable_amount = $total_amount;
    }

} else {

    $payable_amount = $remaining_amount;
}

try {

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT LINK
    |--------------------------------------------------------------------------
    */

    $payment = $api->paymentLink->create([

        'amount' => (int)($payable_amount * 100),

        'currency' => 'INR',

        'accept_partial' => false,

        'description' => 'Payment for Order #' . $order['order_code'],

        'customer' => [
            'name' => 'Customer'
        ],

        'notify' => [
            'sms' => false,
            'email' => false
        ],

        'reminder_enable' => false,

        'callback_url' => 'http://localhost/sogasu_28_05_26/admin/payment-verify.php',

        'callback_method' => 'get'

    ]);

    /*
    |--------------------------------------------------------------------------
    | PAYMENT LINK DETAILS
    |--------------------------------------------------------------------------
    */

    $payment_link_id = $payment->id;

    $payment_link = $payment->short_url;

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER
    |--------------------------------------------------------------------------
    */

    $stmtUpdate = $pdo->prepare("
        UPDATE {$table}
        SET
            razorpay_payment_link_id = ?,
            payment_link = ?
        WHERE id = ?
    ");

    $stmtUpdate->execute([
        $payment_link_id,
        $payment_link,
        $order_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | REDIRECT TO PAYMENT PAGE
    |--------------------------------------------------------------------------
    */

    header("Location: " . $payment_link);
    exit;

} catch (Exception $e) {

    die("Payment Link Error: " . $e->getMessage());
}

