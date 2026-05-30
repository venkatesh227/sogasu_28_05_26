<?php

require_once '../includes/db.php';
require_once '../includes/razorpay-config.php';

if (
    isset($_GET['razorpay_payment_id']) &&
    isset($_GET['razorpay_payment_link_id'])
) {

    $paymentId = $_GET['razorpay_payment_id'];

    $paymentLinkId = $_GET['razorpay_payment_link_id'];
    $stmtOrder = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE razorpay_payment_link_id = ?
    LIMIT 1
");

    $stmtOrder->execute([$paymentLinkId]);

    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found.");
    }

    try {

        // Fetch payment details from Razorpay
        $payment = $api->payment->fetch($paymentId);

        // Verify payment status
        if ($payment->status === 'captured') {
            $total_amount = (float) $order['total_amount'];

            $already_paid = (float) $order['paid_amount'];

            $advance_amount = (float) $order['advance_amount'];

            $remaining_amount = $total_amount - $already_paid;
            if ($already_paid <= 0) {

                $current_payment_amount =
                    $advance_amount > 0
                    ? $advance_amount
                    : $total_amount;

            } else {

                $current_payment_amount = $remaining_amount;
            }
            if ($already_paid <= 0 && $advance_amount > 0) {

                $payment_type = 'advance';

            } elseif (($already_paid + $current_payment_amount) >= $total_amount) {

                $payment_type = 'full';

            } else {

                $payment_type = 'partial';
            }
            $stmtCheck = $pdo->prepare("
                SELECT id
                FROM order_payments
                WHERE razorpay_payment_id = ?
                LIMIT 1
            ");

            $stmtCheck->execute([$paymentId]);

            $existingPayment = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingPayment) {

                header("Location: orders.php?payment=already_verified");
                exit;
            }
            $stmtPayment = $pdo->prepare("
            INSERT INTO order_payments (
                order_id,
                razorpay_payment_id,
                transaction_id,
                payment_mode,
                payment_type,
                amount,
                payment_status,
                payment_response,
                paid_at,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $stmtPayment->execute([
                $order['id'],
                $paymentId,
                $paymentId,
                $payment->method,
                $payment_type,
                $current_payment_amount,
                'success',
                json_encode($payment->toArray()),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
            $new_paid_amount = $already_paid + $current_payment_amount;
            if ($new_paid_amount <= 0) {

                $new_payment_status = 'pending';

            } elseif ($new_paid_amount < $total_amount) {

                $new_payment_status = 'partially_paid';

            } else {

                $new_payment_status = 'paid';
            }
            $stmtUpdate = $pdo->prepare("
                UPDATE orders
                SET
                    paid_amount = ?,
                    payment_status = ?,
                    razorpay_payment_id = ?,
                    paid_at = ?,
                    payment_response = ?
                WHERE id = ?
            ");

            $stmtUpdate->execute([
                $new_paid_amount,
                $new_payment_status,
                $paymentId,
                date('Y-m-d H:i:s'),
                json_encode($payment->toArray()),
                $order['id']
            ]);
            header("Location: orders.php?payment=verified");
            exit;

        } else {

            $stmtFailed = $pdo->prepare("
            INSERT INTO order_payments (
                order_id,
                razorpay_payment_id,
                transaction_id,
                payment_mode,
                payment_type,
                amount,
                payment_status,
                payment_response,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $stmtFailed->execute([
                $order['id'],
                $paymentId,
                $paymentId,
                $payment->method,
                'partial',
                0,
                'failed',
                json_encode($payment->toArray()),
                date('Y-m-d H:i:s')
            ]);

            die("Payment not captured.");

        }

    } catch (Exception $e) {

        die("Verification Error: " . $e->getMessage());
    }
}

echo "Invalid payment response.";