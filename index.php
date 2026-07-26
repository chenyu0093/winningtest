<?php
session_start();
require_once 'session.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$emp_department = $_SESSION['user']['Department'] ?? '';
$logged_in_name = $_SESSION['user']['English_Name'] ?? '';
$logged_in_emp_no = $_SESSION['user']['User_Id'] ?? '';

$db_host = '127.0.0.1';
$db_name = 'winning'; 
$db_user = 'root';
$db_pass = ''; 

$requests = [];
$current_tab = $_GET['tab'] ?? 'pending';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if (!empty($emp_department)) {
        if ($current_tab === 'received') {
            // 已接收：同部門且狀態非 Pending（不限接收者是誰）
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status != 'Pending' ORDER BY T_Updated_At DESC";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->execute([
                ':department' => $emp_department
            ]);
        } else {
            // 待接收：同部門且狀態為 Pending
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status = 'Pending' ORDER BY T_Created_At DESC";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->execute([':department' => $emp_department]);
        }
        $requests = $reqStmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Management Interface</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="style_index.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="toggle-btn" id="toggleBtn" title="Expand/Collapse Menu">
                <span class="material-icons-round">menu</span>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li><a onclick="focusSearch()" title="Search"><span class="material-icons-round">search</span><span class="nav-text">Search</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a href="#" class="active" onclick="switchTab(this, 'Inbox')"><span class="material-icons-round">mail</span><span class="nav-text">Inbox</span></a></li>
            <li><a href="#" onclick="switchTab(this, 'Requests')"><span class="material-icons-round">note_add</span><span class="nav-text">Requests</span></a></li>
            <li><a href="#" onclick="switchTab(this, 'Pending Approval')"><span class="material-icons-round">hourglass_top</span><span class="nav-text">Pending Approval</span></a></li>
            <li><a href="#" onclick="switchTab(this, 'Pending Acceptance')"><span class="material-icons-round">fact_check</span><span class="nav-text">Pending Acceptance</span></a></li>
            <li><a href="#" onclick="switchTab(this, 'Closed')"><span class="material-icons-round">task_alt</span><span class="nav-text">Closed</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="top-navbar">
            <h2 id="pageTitle">Inbox</h2>
            <div class="search-box">
                <span class="material-icons-round">search</span>
                <input type="text" id="searchInput" placeholder="Search data..." oninput="handleSearch(this.value)">
            </div>
        </header>

        <div class="content-body">
            <div class="container">
                
                <!-- Gmail 風格分頁籤 -->
                <div class="gmail-tabs">
                    <a href="index.php?tab=pending" class="gmail-tab-item <?php echo ($current_tab === 'pending') ? 'active' : ''; ?>">
                        <span class="material-icons-round">inbox</span> 待接收
                    </a>
                    <a href="index.php?tab=received" class="gmail-tab-item <?php echo ($current_tab === 'received') ? 'active' : ''; ?>">
                        <span class="material-icons-round">check_circle</span> 已接收
                    </a>
                </div>

                <h3 id="sectionTitle">Inbox Management & View</h3>

                <div id="sectionContent">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Category</th>
                                <th>Subject</th>
                                <th>Requester</th>
                                <?php if ($current_tab === 'received'): ?>
                                    <th>Receiver</th>
                                <?php endif; ?>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['T_Ticket_No'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Category'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Title'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Requester_Id'] ?? ''); ?></td>
                                        
                                        <?php if ($current_tab === 'received'): ?>
                                            <td><?php echo htmlspecialchars($row['T_Receiver_Id'] ?? ''); ?></td>
                                        <?php endif; ?>

                                        <td><?php echo htmlspecialchars($row['T_Priority'] ?? ''); ?></td>
                                        
                                        <td>
                                            <?php 
                                                if ($current_tab === 'received') {
                                                    echo htmlspecialchars($row['T_Updated_At'] ?? $row['T_Created_At'] ?? ''); 
                                                } else {
                                                    echo htmlspecialchars($row['T_Created_At'] ?? ''); 
                                                }
                                            ?>
                                        </td>
                                        
                                        <td>
                                            <?php if ($current_tab === 'pending'): ?>
                                                <form action="accept_task.php" method="POST" style="margin:0;">
                                                    <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                    <button type="submit" class="btn-accept">接收</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #28a745; font-weight: 500;"><?php echo htmlspecialchars($row['T_Status'] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($current_tab === 'received') ? '8' : '7'; ?>" style="text-align: center; color: #666;">
                                        No data records available for "Inbox".
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script src="script.js"></script>
</body>
</html>