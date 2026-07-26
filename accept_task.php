<?php
session_start();
require_once 'session.php'; // 引入共用檔

$db_host = '127.0.0.1';
$db_name = 'winning'; 
$db_user = 'root';
$db_pass = ''; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t_id = $_POST['T_Id'] ?? null;
    
    // 直接抓取 session.php 裡對應好的真實登入編號
    $emp_no = $logged_in_emp_no; 

    if ($t_id) {
        $sql = "UPDATE requests 
                SET T_Receiver_Id = :emp_no, 
                    T_Status = 'In Progress', 
                    T_Updated_At = NOW() 
                WHERE T_Id = :t_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':emp_no' => $emp_no,
            ':t_id' => $t_id
        ]);
    }
}

header("Location: index.php");
exit;
?>