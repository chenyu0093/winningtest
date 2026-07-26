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

// 取得使用者選擇每頁顯示幾筆（預設為 10 筆）
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;

$in_progress_count = 0;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if (!empty($emp_department)) {
        // 計算當前登入者正在處理中的資料筆數 (In Progress)
        $countSql = "SELECT COUNT(*) FROM requests WHERE TRIM(T_Receiver_Id) = TRIM(:emp_no) AND T_Status = 'In Progress'";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([':emp_no' => $logged_in_emp_no]);
        $in_progress_count = $countStmt->fetchColumn();

        if ($current_tab === 'approval') {
            // Pending Approval：抓取當前登入者接收且狀態為 In Progress，依時間最新排最上面，限制筆數
            $sql = "SELECT * FROM requests WHERE TRIM(T_Receiver_Id) = TRIM(:emp_no) AND T_Status = 'In Progress' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':emp_no', $logged_in_emp_no, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'received') {
            // 已接收：同部門且狀態非 Pending，依時間最新排最上面，限制筆數
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status != 'Pending' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':department', $emp_department, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'unassigned') {
            // 待指派：T_Appoint_Id 有資料，依時間最新排最上面，限制筆數
            $sql = "SELECT * FROM requests WHERE T_Appoint_Id IS NOT NULL AND T_Appoint_Id != '' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'pending') {
            // 待接收：同部門且狀態為 Pending，依時間最新排最上面，限制筆數
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status = 'Pending' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':department', $emp_department, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

$total_records = count($requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Management Interface</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="style_index.css">
    <style>
        .container {
            max-width: 100% !important;
            width: 100% !important;
        }
        .header-pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .header-pagination-wrapper h3 {
            margin: 0;
        }
        .pagination-box {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        .pagination-buttons {
            display: flex;
            gap: 5px;
        }
        .pagination-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #888;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .pagination-btn:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        /* --- 側邊欄選單右側的數量徽章樣式 --- */
        .nav-badge {
            margin-left: auto;
            background-color: #1a73e8;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            min-width: 20px;
            text-align: center;
        }
        .sidebar-menu li a.active .nav-badge {
            background-color: #041e49;
            color: #fff;
        }

        /* --- 右下角分頁控制區 --- */
        .bottom-pagination {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e1e3e1;
            font-size: 14px;
            color: #555;
        }
        .page-size-selector select {
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-left: 5px;
            outline: none;
            color: #333;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .modal-box {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            width: 350px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .modal-box h3 { margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; }
        .modal-box select { width: 100%; padding: 8px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; }
        .modal-buttons button { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-confirm { background: #007bff; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        .action-group { display: flex; gap: 5px; align-items: center; }
        .btn-assign { background: #ffc107; color: #000; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-assign:hover { background: #e0a800; }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="toggle-btn" id="toggleBtn" title="Expand/Collapse Menu">
                <span class="material-icons-round">menu</span>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li><a href="#" class="<?php echo ($current_tab === 'requests') ? 'active' : ''; ?>" onclick="switchTab(this, 'Requests')"><span class="material-icons-round">note_add</span><span class="nav-text">Requests</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a href="index.php?tab=pending" class="<?php echo ($current_tab === 'pending' || $current_tab === 'received' || $current_tab === 'unassigned') ? 'active' : ''; ?>" onclick="switchTab(this, 'Inbox')"><span class="material-icons-round">mail</span><span class="nav-text">Inbox</span></a></li>
            <li>
                <a href="index.php?tab=approval" class="<?php echo ($current_tab === 'approval') ? 'active' : ''; ?>" onclick="switchTab(this, 'Pending Approval')">
                    <span class="material-icons-round">hourglass_top</span>
                    <span class="nav-text">Pending Approval</span>
                    <span class="nav-badge"><?php echo $in_progress_count; ?></span>
                </a>
            </li>
            <li><a href="#" class="<?php echo ($current_tab === 'acceptance') ? 'active' : ''; ?>" onclick="switchTab(this, 'Pending Acceptance')"><span class="material-icons-round">fact_check</span><span class="nav-text">Pending Acceptance</span></a></li>
            <li><a href="#" class="<?php echo ($current_tab === 'closed') ? 'active' : ''; ?>" onclick="switchTab(this, 'Closed')"><span class="material-icons-round">task_alt</span><span class="nav-text">Closed</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a onclick="focusSearch()" title="Search"><span class="material-icons-round">search</span><span class="nav-text">Search</span></a></li>
        </ul>

        <!-- 側邊欄底部的登出按鈕 -->
        <div class="sidebar-footer" style="margin-top: auto; padding-top: 10px;">
            <a href="logout.php" title="Logout" style="display: flex; align-items: center; gap: 16px; padding: 12px 18px; border-radius: 99px; text-decoration: none; color: #d93025; font-size: 14px; font-weight: 500; white-space: nowrap; transition: background-color 0.2s ease;">
                <span class="material-icons-round" style="font-size: 20px; flex-shrink: 0;">logout</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-navbar">
            <h2 id="pageTitle"><?php echo ($current_tab === 'approval') ? 'Pending Approval' : 'Inbox'; ?></h2>
            
            <!-- 右上角維持顯示登入者編號、姓名、部門 -->
            <div class="user-info-box">
                <span class="material-icons-round">account_circle</span>
                <div class="user-info-detail">
                    <span>編號: <strong><?php echo htmlspecialchars($logged_in_emp_no); ?></strong></span>
                    <span>|</span>
                    <span>姓名: <strong><?php echo htmlspecialchars($logged_in_name); ?></strong></span>
                    <span>|</span>
                    <span>部門: <strong><?php echo htmlspecialchars($emp_department); ?></strong></span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="container">
                
                <?php if ($current_tab !== 'approval'): ?>
                <!-- Gmail 風格分頁籤 -->
                <div class="gmail-tabs">
                    <a href="index.php?tab=pending&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'pending') ? 'active' : ''; ?>">
                        <span class="material-icons-round">inbox</span> 待接收
                    </a>
                    <a href="index.php?tab=received&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'received') ? 'active' : ''; ?>">
                        <span class="material-icons-round">check_circle</span> 已接收
                    </a>
                    <a href="index.php?tab=unassigned&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'unassigned') ? 'active' : ''; ?>">
                        <span class="material-icons-round">assignment_turned_in</span> 待指派
                    </a>
                </div>
                <?php endif; ?>

                <!-- 標題與右上角分頁資訊 -->
                <div class="header-pagination-wrapper">
                    <h3 id="sectionTitle"><?php echo ($current_tab === 'approval') ? 'Pending Approval Management & View' : 'Inbox Management & View'; ?></h3>
                    <div class="pagination-box">
                        <span>1-<?php echo min($total_records, $limit); ?> 列 (共 <?php echo number_format($total_records); ?> 列)</span>
                        <div class="pagination-buttons">
                            <button type="button" class="pagination-btn"><span class="material-icons-round" style="font-size: 18px;">chevron_left</span></button>
                            <button type="button" class="pagination-btn"><span class="material-icons-round" style="font-size: 18px;">chevron_right</span></button>
                        </div>
                    </div>
                </div>

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
                                <?php elseif ($current_tab === 'unassigned'): ?>
                                    <th>Assigner</th>
                                    <th>Assigned Dept</th>
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
                                        <?php elseif ($current_tab === 'unassigned'): ?>
                                            <td><?php echo htmlspecialchars($row['T_Appoint_Id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['T_AP'] ?? ''); ?></td>
                                        <?php endif; ?>

                                        <td><?php echo htmlspecialchars($row['T_Priority'] ?? ''); ?></td>
                                        
                                        <!-- 日期統一抓取 T_Updated_At -->
                                        <td><?php echo htmlspecialchars($row['T_Updated_At'] ?? $row['T_Created_At'] ?? ''); ?></td>
                                        
                                        <td>
                                            <?php if ($current_tab === 'pending'): ?>
                                                <div class="action-group">
                                                    <form action="accept_task.php" method="POST" style="margin:0;">
                                                        <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                        <button type="submit" class="btn-accept">接收</button>
                                                    </form>
                                                    <button type="button" class="btn-assign" onclick="openAssignModal(<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>)">指派</button>
                                                </div>
                                            <?php elseif ($current_tab === 'unassigned'): ?>
                                                <?php if ($row['T_Status'] === 'Pending'): ?>
                                                    <span style="color: #f0ad4e; font-weight: 500;">Pending</span>
                                                <?php else: ?>
                                                    <span style="color: #28a745; font-weight: 500;"><?php echo htmlspecialchars($row['T_Receiver_Id'] ?? ''); ?></span>
                                                <?php endif; ?>
                                            <?php elseif ($current_tab === 'approval'): ?>
                                                <span style="color: #007bff; font-weight: 500;"><?php echo htmlspecialchars($row['T_Status'] ?? ''); ?></span>
                                            <?php else: ?>
                                                <span style="color: #28a745; font-weight: 500;"><?php echo htmlspecialchars($row['T_Status'] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($current_tab === 'received') ? '8' : (($current_tab === 'unassigned') ? '9' : '7'); ?>" style="text-align: center; color: #666;">
                                        No data records available for "<?php echo ($current_tab === 'approval') ? 'Pending Approval' : 'Inbox'; ?>".
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- 右下角分頁控制區 (選擇筆數後自動重新整理帶入參數) -->
                    <div class="bottom-pagination">
                        <div class="page-size-selector">
                            每頁顯示
                            <select id="pageSize" onchange="changePageSize(this.value)">
                                <option value="10" <?php echo ($limit == 10) ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                            </select>
                            筆
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- 懸浮視窗 (Modal) -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-box">
            <h3>選擇分派部門</h3>
            <form id="assignForm" action="accept_task.php" method="POST">
                <input type="hidden" name="T_Id" id="modal_T_Id">
                <select name="T_AP" id="modal_T_AP" required>
                    <option value="IT">IT</option>
                    <option value="Admin">Admin</option>
                    <option value="Services">Services</option>
                    <option value="Academic">Academic</option>
                </select>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeAssignModal()">取消</button>
                    <button type="submit" class="btn-confirm">確定</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function openAssignModal(taskId) {
            document.getElementById('modal_T_Id').value = taskId;
            document.getElementById('assignModal').style.display = 'flex';
        }
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        // 當右下角下拉選單切換筆數時，自動重新載入網頁並帶入對應分頁與筆數參數
        function changePageSize(size) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', size);
            if (!urlParams.has('tab')) {
                urlParams.set('tab', '<?php echo $current_tab; ?>');
            }
            window.location.href = 'index.php?' + urlParams.toString();
        }
    </script>
</body>
</html>