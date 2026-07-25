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
        $requester_id = (int)($input['requester_id'] ?? 1); 
        $priority = $input['priority'] ?? 'Medium';

        $ticket_no = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);

        if ($type === 'Notice') {
            $absence_date = $input['absence_date'] ?? date('Y-m-d');
            $period = (int)($input['period'] ?? 1);
            $day_of_week = date('N', strtotime($absence_date));

            $stmt = $pdo->prepare("
                SELECT teacher_id FROM schedules 
                WHERE student_id = :student_id 
                  AND day_of_week = :dow 
                  AND period = :period 
                  AND :absence_date BETWEEN start_date AND end_date
                LIMIT 1
            ");
            $stmt->execute([
                ':student_id' => $requester_id,
                ':dow' => $day_of_week,
                ':period' => $period,
                ':absence_date' => $absence_date
            ]);
            $schedule = $stmt->fetch();

            if (!$schedule) {
                echo json_encode(['status' => 'error', 'message' => 'No assigned teacher found for this period.']);
                exit;
            }

            $recipient_id = $schedule['teacher_id'];
            $status = 'Pending_Acknowledge';

            $insertStmt = $pdo->prepare("
                INSERT INTO requests (ticket_no, type, title, description, priority, status, requester_id, recipient_id)
                VALUES (:ticket_no, 'Notice', :title, :description, :priority, :status, :requester_id, :recipient_id)
            ");
            $insertStmt->execute([
                ':ticket_no' => $ticket_no,
                ':title' => $title,
                ':description' => "Absence Date: $absence_date (Period $period) - $description",
                ':priority' => $priority,
                ':status' => $status,
                ':requester_id' => $requester_id,
                ':recipient_id' => $recipient_id
            ]);

            echo json_encode([
                'status' => 'success', 
                'message' => 'Leave request submitted successfully.',
                'ticket_no' => $ticket_no,
                'assigned_teacher_id' => $recipient_id
            ]);

        } else {
            $status = 'Pending_Approval';

            $insertStmt = $pdo->prepare("
                INSERT INTO requests (ticket_no, type, title, description, priority, status, requester_id)
                VALUES (:ticket_no, 'Task', :title, :description, :priority, :status, :requester_id)
            ");
            $insertStmt->execute([
                ':ticket_no' => $ticket_no,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':status' => $status,
                ':requester_id' => $requester_id
            ]);

            echo json_encode([
                'status' => 'success', 
                'message' => 'Task request submitted for approval.',
                'ticket_no' => $ticket_no
            ]);
        }
        break;

    case 'get_requests':
        $stmt = $pdo->query("
            SELECT r.*, u.full_name as requester_name 
            FROM requests r 
            JOIN users u ON r.requester_id = u.user_id 
            ORDER BY r.created_at DESC
        ");
        $requests = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $requests]);
        break;

    // 🔐 依據 Student_No / Emp_No 直接進行 ID 登入
    case 'login':
        $userId = trim($input['id'] ?? '');

        if (empty($userId)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter Employee ID or Student ID']);
            exit;
        }

        // 1. 先查詢 Students 表 (使用 S_Student_No)
        $stmt = $pdo->prepare("
            SELECT S_Id as Id, 'Student' as Table_Type, S_Status as Status, 
                   S_English_Name as English_Name, 'student' as Role 
            FROM Students 
            WHERE S_Student_No = :userId 
            LIMIT 1
        ");
        $stmt->execute([':userId' => $userId]);
        $user = $stmt->fetch();

        // 2. 若非學生，則查詢 Employees 表 (使用 E_Emp_No)
        if (!$user) {
            $stmt = $pdo->prepare("
                SELECT E_Id as Id, 'Employee' as Table_Type, E_Status as Status, 
                       E_English_Name as English_Name, 'employee' as Role 
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

        // 5. 登入成功：寫入 Session
        $_SESSION['user'] = [
            'User_Id'      => $user['Id'],
            'English_Name' => $user['English_Name'],
            'Role'         => $user['Role']
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