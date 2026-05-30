<?php

require_once '../includes/db.php';

$input = file_get_contents("php://input");

$data = json_decode($input, true);

file_put_contents(
    'webhook_log.txt',
    date('Y-m-d H:i:s') . PHP_EOL .
    print_r($data, true) . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

if (isset($data['event']) && $data['event'] === 'payment_link.paid') {

    $paymentEntity = $data['payload']['payment']['entity'];

    $paymentLinkEntity = $data['payload']['payment_link']['entity'];

    $paymentLinkId = $paymentLinkEntity['id'];

    $paymentId = $paymentEntity['id'];

    $amount = $paymentEntity['amount'] / 100;

    $stmt = $pdo->prepare("
        UPDATE orders
        SET
            payment_status = 'paid',
            razorpay_payment_id = ?,
            paid_at = NOW(),
            payment_response = ?
        WHERE razorpay_payment_link_id = ?
    ");

    $stmt->execute([
        $paymentId,
        json_encode($data),
        $paymentLinkId
    ]);
}

http_response_code(200);