<?php
$db_host = '127.0.0.1';
$db_name = 'winning'; 
$db_user = 'root';
$db_pass = ''; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<h2 style='color: green;'>成功連線到資料庫 (winning)！</h2>";
    
    // 測試讀取 requests 資料表
    $stmt = $pdo->query("SELECT * FROM requests");
    $rows = $stmt->fetchAll();
    echo "<p>目前 requests 資料表共有 " . count($rows) . " 筆資料。</p>";
    
    if (count($rows) > 0) {
        echo "<pre>";
        print_r($rows);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>連線失敗：</h2>" . $e->getMessage();
}
?>