<?php

session_start(); // 1. 啟用 Session

header('Content-Type: application/json; charset=utf-8');

// 資料庫連線配置
$db_host = '127.0.0.1';
$db_name = 'winning'; 
$db_user = 'root';
$db_pass = ''; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create_request':
        $type = $input['type'] ?? 'Task';
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';
        $requester_id = $input['requester_id'] ?? 'E001'; 
        $priority = $input['priority'] ?? 'Medium';
        $category = $input['category'] ?? '其他';

        $ticket_no = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);
        $status = 'Pending_Approval'; // 預設狀態

        // 寫入對應 requests 資料表
        $insertStmt = $pdo->prepare("
            INSERT INTO requests (T_Ticket_No, T_Requester_Id, T_Category, T_Title, T_Description, T_Priority, T_Status)
            VALUES (:ticket_no, :requester_id, :category, :title, :description, :priority, :status)
        ");
        $insertStmt->execute([
            ':ticket_no' => $ticket_no,
            ':requester_id' => $requester_id,
            ':category' => $category,
            ':title' => $title,
            ':description' => $description,
            ':priority' => $priority,
            ':status' => $status
        ]);

        echo json_encode([
            'status' => 'success', 
            'message' => 'Request submitted successfully.',
            'ticket_no' => $ticket_no
        ]);
        break;

    case 'get_requests':
        $stmt = $pdo->query("SELECT * FROM requests ORDER BY T_Created_At DESC");
        $requests = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $requests]);
        break;

    // 依據 Student_No / Emp_No 直接進行 ID 登入，並抓取部門
    case 'login':
        $userId = trim($input['id'] ?? '');

        if (empty($userId)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter Employee ID or Student ID']);
            exit;
        }

        // 1. 先查詢 Students 表 (使用 S_Student_No)
        $stmt = $pdo->prepare("
            SELECT S_Id as Id, S_Student_No as Emp_No, 'Student' as Table_Type, S_Status as Status, 
                   S_English_Name as English_Name, 'student' as Role, NULL as Department
            FROM Students 
            WHERE S_Student_No = :userId 
            LIMIT 1
        ");
        $stmt->execute([':userId' => $userId]);
        $user = $stmt->fetch();

        // 2. 若非學生，則查詢 Employees 表 (使用 E_Emp_No，並抓出 E_Department)
        if (!$user) {
            $stmt = $pdo->prepare("
                SELECT E_Id as Id, E_Emp_No as Emp_No, 'Employee' as Table_Type, E_Status as Status, 
                       E_English_Name as English_Name, 'employee' as Role, E_Department as Department 
                FROM Employees 
                WHERE E_Emp_No = :userId 
                LIMIT 1
            ");
            $stmt->execute([':userId' => $userId]);
            $user = $stmt->fetch();
        }

        // 3. ID 不存在
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Employee ID or Student ID']);
            exit;
        }

        // 4. 檢查帳號是否遭停用
        if (isset($user['Status']) && $user['Status'] === 'Disabled') {
            echo json_encode(['status' => 'error', 'message' => 'This account has been disabled. Please contact the administrator.']);
            exit;
        }

        // 5. 登入成功：寫入 Session（包含員工編號與所屬部門）
        $_SESSION['user'] = [
            'User_Id'      => $user['Emp_No'], // 存入編號供後續比對用
            'English_Name' => $user['English_Name'],
            'Role'         => $user['Role'],
            'Department'   => $user['Department'] ?? ''
        ];

        echo json_encode([
            'status' => 'success', 
            'user'   => $_SESSION['user']
        ]);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}