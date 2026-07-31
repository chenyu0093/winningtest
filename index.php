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

// Get records per page and sorting parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;

$sort_by = $_GET['sort'] ?? 'date';
$sort_order = $_GET['order'] ?? 'DESC';

// Determine SQL sorting column
$order_column = "COALESCE(T_Updated_At, T_Created_At)";
if ($sort_by === 'ticket') {
    $order_column = "T_Ticket_No";
} elseif ($sort_by === 'category') {
    $order_column = "T_Category";
} elseif ($sort_by === 'priority') {
    $order_column = "CASE WHEN T_Priority = 'Urgent' THEN 1 WHEN T_Priority = 'High' THEN 2 WHEN T_Priority = 'Medium' THEN 3 ELSE 4 END";
} elseif ($sort_by === 'date') {
    $order_column = "COALESCE(T_Updated_At, T_Created_At)";
}

$direction = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

$in_progress_count = 0;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Handle "Return" action for pending tab
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_task') {
        $t_id = $_POST['T_Id'] ?? '';
        $returnSql = "UPDATE requests SET T_Status = 'Returned', T_Receiver_Id = :receiver_id, T_Updated_At = :updated_at WHERE T_Id = :id";
        $returnStmt = $pdo->prepare($returnSql);
        $returnStmt->execute([
            ':receiver_id' => $logged_in_emp_no,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $t_id
        ]);
        header('Location: index.php?tab=pending');
        exit;
    }

    if (!empty($emp_department)) {
        $countSql = "SELECT COUNT(*) FROM requests WHERE TRIM(T_Receiver_Id) = TRIM(:emp_no) AND T_Status = 'In Progress'";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([':emp_no' => $logged_in_emp_no]);
        $in_progress_count = $countStmt->fetchColumn();

        if ($current_tab === 'approval') {
            $sql = "SELECT * FROM requests WHERE TRIM(T_Receiver_Id) = TRIM(:emp_no) AND T_Status = 'In Progress' ORDER BY $order_column $direction LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':emp_no', $logged_in_emp_no, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'received') {
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status != 'Pending' AND T_Status != 'Draft' ORDER BY $order_column $direction LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':department', $emp_department, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'unassigned') {
            $sql = "SELECT * FROM requests WHERE T_Appoint_Id IS NOT NULL AND T_Appoint_Id != '' AND TRIM(T_AP) = TRIM(:department) ORDER BY $order_column $direction LIMIT :limit";
            $reqStmt = $pdo->prepare($sql);
            $reqStmt->bindValue(':department', $emp_department, PDO::PARAM_STR);
            $reqStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $reqStmt->execute();
            $requests = $reqStmt->fetchAll();
        } elseif ($current_tab === 'pending') {
            $sql = "SELECT * FROM requests WHERE TRIM(T_AP) = TRIM(:department) AND T_Status = 'Pending' ORDER BY $order_column $direction LIMIT :limit";
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
            flex-wrap: wrap;
            gap: 10px;
        }
        .header-pagination-wrapper h3 {
            margin: 0;
        }
        .sort-pagination-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sort-box select {
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-left: 5px;
            outline: none;
            color: #333;
            font-size: 14px;
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
            width: 450px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .modal-box h3 { margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; }
        .modal-box select { width: 100%; padding: 8px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .modal-buttons button { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-confirm { background: #007bff; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        
        .action-group { display: flex; gap: 5px; align-items: center; }
        .btn-assign { background: #ffc107; color: #000; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-assign:hover { background: #e0a800; }
        .btn-show { background: #17a2b8; color: #fff; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-show:hover { background: #138496; }
        .btn-return { background: #dc3545; color: #fff; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-return:hover { background: #c82333; }

        .detail-row { margin-bottom: 12px; font-size: 14px; }
        .detail-row strong { color: #555; display: inline-block; width: 100px; }
        .detail-content-box { background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 5px; white-space: pre-wrap; word-break: break-all; }
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
            <li><a href="requests.php"><span class="material-icons-round">note_add</span><span class="nav-text">Requests</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a href="index.php?tab=pending" class="<?php echo ($current_tab === 'pending' || $current_tab === 'received' || $current_tab === 'unassigned') ? 'active' : ''; ?>"><span class="material-icons-round">mail</span><span class="nav-text">Inbox</span></a></li>
            <li>
                <a href="index.php?tab=approval" class="<?php echo ($current_tab === 'approval') ? 'active' : ''; ?>">
                    <span class="material-icons-round">hourglass_top</span>
                    <span class="nav-text">Pending Approval</span>
                    <span class="nav-badge"><?php echo $in_progress_count; ?></span>
                </a>
            </li>
            <li><a href="#"><span class="material-icons-round">fact_check</span><span class="nav-text">Pending Acceptance</span></a></li>
            <li><a href="#"><span class="material-icons-round">task_alt</span><span class="nav-text">Closed</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a onclick="focusSearch()" title="Search"><span class="material-icons-round">search</span><span class="nav-text">Search</span></a></li>
        </ul>

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
            
            <div class="user-info-box">
                <span class="material-icons-round">account_circle</span>
                <div class="user-info-detail">
                    <span>ID: <strong><?php echo htmlspecialchars($logged_in_emp_no); ?></strong></span>
                    <span>|</span>
                    <span>Name: <strong><?php echo htmlspecialchars($logged_in_name); ?></strong></span>
                    <span>|</span>
                    <span>Dept: <strong><?php echo htmlspecialchars($emp_department); ?></strong></span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="container">
                
                <?php if ($current_tab !== 'approval'): ?>
                <div class="gmail-tabs">
                    <a href="index.php?tab=pending&limit=<?php echo $limit; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>" class="gmail-tab-item <?php echo ($current_tab === 'pending') ? 'active' : ''; ?>">
                        <span class="material-icons-round">inbox</span> Pending
                    </a>
                    <a href="index.php?tab=received&limit=<?php echo $limit; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>" class="gmail-tab-item <?php echo ($current_tab === 'received') ? 'active' : ''; ?>">
                        <span class="material-icons-round">check_circle</span> Received
                    </a>
                    <a href="index.php?tab=unassigned&limit=<?php echo $limit; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>" class="gmail-tab-item <?php echo ($current_tab === 'unassigned') ? 'active' : ''; ?>">
                        <span class="material-icons-round">assignment_turned_in</span> Unassigned
                    </a>
                </div>
                <?php endif; ?>

                <div class="header-pagination-wrapper">
                    <h3 id="sectionTitle"><?php echo ($current_tab === 'approval') ? 'Pending Approval Management & View' : 'Inbox Management & View'; ?></h3>
                    
                    <div class="sort-pagination-group">
                        <div class="sort-box">
                            Sort By:
                            <select id="sortBy" onchange="changeSorting()">
                                <option value="ticket" <?php echo ($sort_by === 'ticket') ? 'selected' : ''; ?>>Ticket No</option>
                                <option value="category" <?php echo ($sort_by === 'category') ? 'selected' : ''; ?>>Category</option>
                                <option value="priority" <?php echo ($sort_by === 'priority') ? 'selected' : ''; ?>>Priority</option>
                                <option value="date" <?php echo ($sort_by === 'date') ? 'selected' : ''; ?>>Date</option>
                            </select>
                            <select id="sortOrder" onchange="changeSorting()">
                                <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending (High to Low)</option>
                                <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending (Low to High)</option>
                            </select>
                        </div>

                        <div class="pagination-box">
                            <span>1-<?php echo min($total_records, $limit); ?> of (Total <?php echo number_format($total_records); ?>)</span>
                            <div class="pagination-buttons">
                                <button type="button" class="pagination-btn"><span class="material-icons-round" style="font-size: 18px;">chevron_left</span></button>
                                <button type="button" class="pagination-btn"><span class="material-icons-round" style="font-size: 18px;">chevron_right</span></button>
                            </div>
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
                                        <td>
                                            <?php 
                                                $requester_id = trim($row['T_Requester_Id'] ?? '');
                                                $current_user_id = trim($logged_in_emp_no ?? '');
                                                if (!empty($current_user_id) && $requester_id === $current_user_id) {
                                                    echo 'You';
                                                } else {
                                                    echo htmlspecialchars($row['T_Requester_Id'] ?? '');
                                                }
                                            ?>
                                        </td>
                                        
                                        <?php if ($current_tab === 'received'): ?>
                                            <td><?php echo htmlspecialchars($row['T_Receiver_Id'] ?? ''); ?></td>
                                        <?php elseif ($current_tab === 'unassigned'): ?>
                                            <td><?php echo htmlspecialchars($row['T_Appoint_Id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['T_AP'] ?? ''); ?></td>
                                        <?php endif; ?>

                                        <td><?php echo htmlspecialchars($row['T_Priority'] ?? ''); ?></td>
                                        
                                        <td><?php echo htmlspecialchars($row['T_Updated_At'] ?? $row['T_Created_At'] ?? ''); ?></td>
                                        
                                        <td>
                                            <?php 
                                                $requester_id = trim($row['T_Requester_Id'] ?? '');
                                                $current_user_id = trim($logged_in_emp_no ?? '');
                                                $is_self = (!empty($current_user_id) && $requester_id === $current_user_id);
                                            ?>
                                            <?php if ($current_tab === 'pending'): ?>
                                                <?php if (!$is_self): ?>
                                                    <div class="action-group">
                                                        <form action="accept_task.php" method="POST" style="margin:0;">
                                                            <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                            <button type="submit" class="btn-accept">Accept</button>
                                                        </form>
                                                        <button type="button" class="btn-assign" onclick="openAssignModal(<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>)">Assign</button>
                                                        <button type="button" class="btn-show" onclick='openDetailModal(<?php echo json_encode($row); ?>)'>View</button>
                                                        <form action="index.php?tab=pending" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to return this request?');">
                                                            <input type="hidden" name="action" value="return_task">
                                                            <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                            <button type="submit" class="btn-return">Return</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="action-group">
                                                        <button type="button" class="btn-show" onclick='openDetailModal(<?php echo json_encode($row); ?>)'>View</button>
                                                    </div>
                                                <?php endif; ?>
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

                    <div class="bottom-pagination">
                        <div class="page-size-selector">
                            Rows per page:
                            <select id="pageSize" onchange="changePageSize(this.value)">
                                <option value="10" <?php echo ($limit == 10) ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ($limit == 20) ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Assign Department Modal -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-box">
            <h3>Select Department to Assign</h3>
            <form id="assignForm" action="accept_task.php" method="POST">
                <input type="hidden" name="T_Id" id="modal_T_Id">
                <select name="T_AP" id="modal_T_AP" required>
                    <?php 
                        $departments = ['IT', 'Admin', 'Services', 'Academic'];
                        foreach ($departments as $dept) {
                            if (strcasecmp(trim($dept), trim($emp_department)) === 0) {
                                continue;
                            }
                            echo '<option value="' . $dept . '">' . $dept . '</option>';
                        }
                    ?>
                </select>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn-confirm">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Detail Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-box" style="width: 500px;">
            <h3>Request Details</h3>
            <div class="detail-row">
                <strong>Ticket No:</strong> <span id="detail_ticket"></span>
            </div>
            <div class="detail-row">
                <strong>Date:</strong> <span id="detail_date"></span>
            </div>
            <div class="detail-row">
                <strong>Category:</strong> <span id="detail_category"></span>
            </div>
            <div class="detail-row">
                <strong>Status:</strong> <span id="detail_status" style="color: #007bff; font-weight: 500;"></span>
            </div>
            <div class="detail-row">
                <strong>Title:</strong> <span id="detail_title"></span>
            </div>
            <div class="detail-row">
                <strong>Description:</strong>
                <div class="detail-content-box" id="detail_description"></div>
            </div>

            <div class="modal-buttons" style="margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeDetailModal()">Close</button>
            </div>
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

        function openDetailModal(row) {
            document.getElementById('detail_ticket').innerText = row.T_Ticket_No || '';
            document.getElementById('detail_date').innerText = row.T_Created_At || row.T_Updated_At || '';
            document.getElementById('detail_category').innerText = row.T_Category || '';
            document.getElementById('detail_status').innerText = row.T_Status || '';
            document.getElementById('detail_title').innerText = row.T_Title || '';
            document.getElementById('detail_description').innerText = row.T_Description || '';

            document.getElementById('detailModal').style.display = 'flex';
        }
        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        function changePageSize(size) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', size);
            if (!urlParams.has('tab')) {
                urlParams.set('tab', '<?php echo $current_tab; ?>');
            }
            window.location.href = 'index.php?' + urlParams.toString();
        }

        function changeSorting() {
            const sortBy = document.getElementById('sortBy').value;
            const sortOrder = document.getElementById('sortOrder').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', sortBy);
            urlParams.set('order', sortOrder);
            if (!urlParams.has('tab')) {
                urlParams.set('tab', '<?php echo $current_tab; ?>');
            }
            window.location.href = 'index.php?' + urlParams.toString();
        }
    </script>
</body>
</html>