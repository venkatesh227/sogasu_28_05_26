<?php
session_start();

$data = json_decode(file_get_contents("php://input"), true);

$_SESSION['order'] = [
    'category_id' => $data['category_id'],
    'sub_category_id' => $data['sub_category_id'],
    'visit_type' => $data['visit_type'],
    'date' => $data['date'],
    'time' => $data['time']
];

echo json_encode(['success' => true]);