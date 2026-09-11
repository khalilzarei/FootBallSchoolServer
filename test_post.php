<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'POST request received',
        'data' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'body' => file_get_contents('php://input'),
        ],
    ]);
    exit;
}

echo 'Send a POST request to test';