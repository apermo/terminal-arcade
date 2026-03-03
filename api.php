<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0700, true);
    // Block direct web access to stored JSON files
    file_put_contents("$dataDir/.htaccess", "Deny from all\n");
}

$action = $_GET['action'] ?? '';

// Enforce POST for write endpoints
if (str_starts_with($action, 'save-') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Max payload sizes (bytes) per save endpoint
$sizeLimits = [
    'save-model'    => 102400,  // 100 KB
    'save-training' => 524288,  // 512 KB
    'save-stats'    => 2048,    //   2 KB
];

/**
 * Read request body, validate JSON + structure, write to disk.
 */
function saveJson(string $file, int $maxBytes, callable $validate): void {
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || $raw === '') {
        http_response_code(400);
        echo json_encode(['error' => 'No data']);
        return;
    }
    if (strlen($raw) > $maxBytes) {
        http_response_code(413);
        echo json_encode(['error' => 'Payload too large']);
        return;
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    if (!$validate($data)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid data structure']);
        return;
    }

    // Re-encode to strip anything json_decode+encode wouldn't preserve
    file_put_contents($file, json_encode($data), LOCK_EX);
    echo json_encode(['ok' => true]);
}

function loadJson(string $file, mixed $default): void {
    if (file_exists($file) && is_readable($file)) {
        readfile($file);
    } else {
        echo json_encode($default);
    }
}

switch ($action) {
    case 'load-model':
        loadJson("$dataDir/model.json", null);
        break;

    case 'save-model':
        saveJson("$dataDir/model.json", $sizeLimits[$action], fn($d) =>
            is_array($d)
            && isset($d['s'], $d['w'], $d['b'])
            && is_array($d['s']) && is_array($d['w']) && is_array($d['b'])
        );
        break;

    case 'load-training':
        loadJson("$dataDir/training.json", []);
        break;

    case 'save-training':
        saveJson("$dataDir/training.json", $sizeLimits[$action], fn($d) =>
            is_array($d) && (empty($d) || (
                isset($d[0]['input'], $d[0]['output'])
                && is_array($d[0]['input']) && is_array($d[0]['output'])
            ))
        );
        break;

    case 'load-stats':
        loadJson("$dataDir/stats.json", ['games' => 0, 'best' => 0, 'recent' => []]);
        break;

    case 'save-stats':
        saveJson("$dataDir/stats.json", $sizeLimits[$action], fn($d) =>
            is_array($d)
            && isset($d['games'], $d['best'], $d['recent'])
            && is_numeric($d['games']) && is_numeric($d['best'])
            && is_array($d['recent'])
            && count($d['recent']) <= 50
        );
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
