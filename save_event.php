<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (isset($data['title'])) {
    $dbFile = __DIR__ . '/events.json';
    
    $events = [];
    if (file_exists($dbFile)) {
        $jsonContent = file_get_contents($dbFile);
        $events = json_decode($jsonContent, true) ?? [];
    }

    // FullCalendarが最も好むフラットな構造で格納
    $newEvent = [
        "id"              => (string)time(), // 文字列型ID
        "title"           => (string)$data['title'],
        "start"           => (string)$data['start_at'], // フロントから届くISO形式をそのまま保持
        "end"             => (string)$data['end_at'],
        "backgroundColor" => "#3b82f6", // 鮮やかなブルー
        "borderColor"     => "#2563eb",
        "allDay"          => (bool)$data['all_day']
    ];

    $events[] = $newEvent;
    file_put_contents($dbFile, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            @$redis->connect('127.0.0.1', 6379);
            $keys = $redis->keys('user:1:events:*');
            if (!empty($keys)) { $redis->del($keys); }
        } catch (Exception $e) {}
    }

    echo json_encode([
        "status" => "success",
        "message" => "予定「" . htmlspecialchars($data['title']) . "」を永続化しました！"
    ]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "タイトルがありません"]);
}