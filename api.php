<?php

header('Content-Type: application/json; charset=utf-8');

// 資料庫連線配置 (已設定為你的 winning 資料庫)
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
                echo json_encode(['status' => 'error', 'message' => '選取的時間段查無對應授課老師！']);
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
                ':description' => "請假日期: $absence_date (第 $period 節) - $description",
                ':priority' => $priority,
                ':status' => $status,
                ':requester_id' => $requester_id,
                ':recipient_id' => $recipient_id
            ]);

            echo json_encode([
                'status' => 'success', 
                'message' => '請假通知已自動指派給對應老師！',
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
                'message' => '維修/任務需求已送出審核！',
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

    default:
        echo json_encode(['status' => 'error', 'message' => '無效的操作指令']);
        break;
}