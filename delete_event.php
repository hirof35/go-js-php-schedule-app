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

if (isset($data['id'])) {
    $dbFile = __DIR__ . '/events.json';
    $targetId = (string)$data['id'];
    
    if (file_exists($dbFile)) {
        $jsonContent = file_get_contents($dbFile);
        $events = json_decode($jsonContent, true) ?? [];
        
        // 指定されたID以外の予定だけを残す（＝削除フィルタリング）
        $filteredEvents = array_filter($events, function($event) use ($targetId) {
            return (string)$event['id'] !== $targetId;
        });
        
        // 配列のインデックス（添字）を0から綺麗に振り直す
        $filteredEvents = array_values($filteredEvents);
        
        // ファイルに書き戻す
        file_put_contents($dbFile, json_encode($filteredEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Redisキャッシュの破棄
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
        "message" => "予定を安全に破棄し、キャッシュをクリアしました。"
    ]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "削除対象のIDが指定されていません。"]);
}