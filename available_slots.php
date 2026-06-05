<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    $date = trim((string)($_GET['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['slots' => []]);
        exit;
    }

    echo json_encode(['slots' => available_slots($pdo, $date)]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['slots' => [], 'error' => 'Database unavailable']);
}
?>
