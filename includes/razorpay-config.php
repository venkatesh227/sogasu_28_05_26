<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Razorpay\Api\Api;

$keyId = 'rzp_test_Sulj5IJllGT6iR';
$keySecret = 'vY8q5uj6iJmkFjtIUbcpvrl5';

$api = new Api($keyId, $keySecret);