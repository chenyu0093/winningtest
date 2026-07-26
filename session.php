<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$emp_department = $_SESSION['user']['Department'] ?? '';
$logged_in_name = $_SESSION['user']['English_Name'] ?? '';

// 正確對應到登入時存的 User_Id
$logged_in_emp_no = $_SESSION['user']['User_Id'] ?? 'EMP001';
?>