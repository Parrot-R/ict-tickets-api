<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test DB connection here if you want
http_response_code(200);
echo json_encode(['status' => 'ok']);