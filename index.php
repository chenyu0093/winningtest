<?php
session_start();

// 1. 檢查是否有登入，若無則導向登入頁面
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$emp_department = $_SESSION['user']['Department'] ?? '';
$logged_in_name = $_SESSION['user']['English_Name'] ?? '';

// 資料庫連線配置
$db_host = '127.0.0.1';
$db_name = 'winning'; 
$db_user = 'root';
$db_pass = ''; 

$requests = [];

try {
    // 2. 建立 PDO 連線
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 3. 根據部門抓取 requests 資料表的資料 (對應正確的欄位名稱 T_Status 與 T_Created_At)
    if (!empty($emp_department)) {
        $reqStmt = $pdo->prepare("SELECT * FROM requests WHERE TRIM(T_Status) = TRIM(:department) ORDER BY T_Created_At DESC");
        $reqStmt->execute([':department' => $emp_department]);
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
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="style_index.css">
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="toggle-btn" id="toggleBtn" title="Expand/Collapse Menu">
                <span class="material-icons-round">menu</span>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a onclick="focusSearch()" title="Search">
                    <span class="material-icons-round">search</span>
                    <span class="nav-text">Search</span>
                </a>
            </li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li>
                <a href="#" class="active" onclick="switchTab(this, 'Inbox')">
                    <span class="material-icons-round">mail</span>
                    <span class="nav-text">Inbox</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, 'Requests')">
                    <span class="material-icons-round">note_add</span>
                    <span class="nav-text">Requests</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, 'Pending Approval')">
                    <span class="material-icons-round">hourglass_top</span>
                    <span class="nav-text">Pending Approval</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, 'Pending Acceptance')">
                    <span class="material-icons-round">fact_check</span>
                    <span class="nav-text">Pending Acceptance</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, 'Closed')">
                    <span class="material-icons-round">task_alt</span>
                    <span class="nav-text">Closed</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <h2 id="pageTitle">Inbox (Welcome, <?php echo htmlspecialchars($logged_in_name); ?> | Dept: <?php echo htmlspecialchars($emp_department); ?>)</h2>
            <div class="search-box">
                <span class="material-icons-round">search</span>
                <input type="text" id="searchInput" placeholder="Search data..." oninput="handleSearch(this.value)">
            </div>
        </header>

        <!-- Content Body -->
        <div class="content-body">
            <div class="container">
                <h3 id="sectionTitle">Inbox Management & View</h3>
                <div id="sectionContent">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Category</th>
                                <th>Subject</th>
                                <th>Requester</th>
                                <th>Priority</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['T_Ticket_No']); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Requester_Id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Priority']); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Created_At']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #666;">No data records found for your department (<?php echo htmlspecialchars($emp_department); ?>).</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- External JS -->
    <script src="script.js"></script>
</body>
</html>