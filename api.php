<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'load-model':
        $file = $dataDir . '/model.json';
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            echo json_encode(null);
        }
        break;

    case 'save-model':
        $data = file_get_contents('php://input');
        if ($data) {
            file_put_contents($dataDir . '/model.json', $data);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No data']);
        }
        break;

    case 'load-training':
        $file = $dataDir . '/training.json';
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            echo json_encode([]);
        }
        break;

    case 'save-training':
        $data = file_get_contents('php://input');
        if ($data) {
            file_put_contents($dataDir . '/training.json', $data);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No data']);
        }
        break;

    case 'load-stats':
        $file = $dataDir . '/stats.json';
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            echo json_encode(['games' => 0, 'best' => 0, 'recent' => []]);
        }
        break;

    case 'save-stats':
        $data = file_get_contents('php://input');
        if ($data) {
            file_put_contents($dataDir . '/stats.json', $data);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No data']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}