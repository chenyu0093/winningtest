<?php
// 啟動 Session
session_start();

// 清除所有 Session 變數
$_SESSION = array();

// 如果有使用 Cookie 來儲存 Session，也一併刪除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 徹底摧毀 Session
session_destroy();

// 導向回登入頁面 (請根據你的登入頁面檔名調整，例如 login.php)
header("Location: login.html");
exit;
?>