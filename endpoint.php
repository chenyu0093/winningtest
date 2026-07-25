<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 資料庫連線配置
$db_host = '127.0.0.1';
$db_name = 'winning';
$db_user = 'root';
$db_pass = ''; // 請依你的密碼調整

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '資料庫連線失敗: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'create_request') {
    // 接收前端 JSON 傳送的資料
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 這裡可加入你的 INSERT SQL 邏輯
    // ...
    
    echo json_encode(['status' => 'success', 'message' => '送出成功！']);
    exit;
} 
else if ($action === 'get_requests') {
    // 這裡可加入你的 SELECT SQL 邏輯來讀取列表
    // 範例回傳格式：
    $data = [
        [
            'ticket_no' => 'T2026072601',
            'type' => 'Task',
            'title' => '302 教室投影機故障',
            'requester_name' => 'David',
            'status' => 'Pending',
            'created_at' => '2026-07-26 12:00:00'
        ]
    ];
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}
else {
    echo json_encode(['status' => 'error', 'message' => '無效的操作']);
}
?>