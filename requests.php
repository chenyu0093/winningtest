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
$current_tab = $_GET['tab'] ?? 'new_request';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;

$in_progress_count = 0;
$error_msg = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Handle create request or save draft
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
        $req_date = $_POST['T_Date'] ?? date('Y-m-d H:i:s');
        $category = $_POST['T_Category'] ?? '';
        $title = $_POST['T_Title'] ?? '';
        $description = $_POST['T_Description'] ?? '';
        $form_action_type = $_POST['form_action_type'] ?? 'submit';

        $status = ($form_action_type === 'draft') ? 'Draft' : 'Pending';
        $target_tab = ($form_action_type === 'draft') ? 'drafts' : 'new_request';
        
        $target_department = 'BOSS187';
        if ($category === '資訊相關') {
            $target_department = 'IT';
        } elseif ($category === '行政相關') {
            $target_department = 'Admin';
        } elseif ($category === '維修相關') {
            $target_department = 'Services';
        }

        $ticket_no = 'REQ-' . date('Ymd') . '-' . sprintf('%03d', rand(1, 999));

        $insertSql = "INSERT INTO requests (T_Ticket_No, T_Category, T_Title, T_Description, T_Requester_Id, T_AP, T_Status, T_Priority, T_Created_At, T_Updated_At) 
                      VALUES (:ticket_no, :category, :title, :description, :requester_id, :department, :status, 'Medium', :created_at, :updated_at)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':ticket_no' => $ticket_no,
            ':category' => $category,
            ':title' => $title,
            ':description' => $description,
            ':requester_id' => $logged_in_emp_no,
            ':department' => $target_department,
            ':status' => $status,
            ':created_at' => $req_date,
            ':updated_at' => date('Y-m-d H:i:s')
        ]);

        header('Location: requests.php?tab=' . $target_tab);
        exit;
    }

    // Handle draft and new request interactions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $t_id = $_POST['T_Id'] ?? '';

        if ($_POST['action'] === 'update_draft') {
            $category = $_POST['T_Category'] ?? '';
            $title = $_POST['T_Title'] ?? '';
            $description = $_POST['T_Description'] ?? '';
            $req_date = $_POST['T_Date'] ?? date('Y-m-d H:i:s');
            $edit_action_type = $_POST['edit_action_type'] ?? 'save';

            $target_department = 'BOSS187';
            if ($category === '資訊相關') {
                $target_department = 'IT';
            } elseif ($category === '行政相關') {
                $target_department = 'Admin';
            } elseif ($category === '維修相關') {
                $target_department = 'Services';
            }

            $new_status = ($edit_action_type === 'send') ? 'Pending' : 'Draft';
            $redirect_tab = ($edit_action_type === 'send') ? 'new_request' : 'drafts';

            $updateSql = "UPDATE requests SET T_Category = :category, T_Title = :title, T_Description = :description, T_AP = :department, T_Status = :status, T_Created_At = :created_at, T_Updated_At = :updated_at WHERE T_Id = :id AND TRIM(T_Requester_Id) = TRIM(:requester_id)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':category' => $category,
                ':title' => $title,
                ':description' => $description,
                ':department' => $target_department,
                ':status' => $new_status,
                ':created_at' => $req_date,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $t_id,
                ':requester_id' => $logged_in_emp_no
            ]);
            header('Location: requests.php?tab=' . $redirect_tab);
            exit;
        } elseif ($_POST['action'] === 'delete_draft') {
            $delSql = "DELETE FROM requests WHERE T_Id = :id AND TRIM(T_Requester_Id) = TRIM(:requester_id) AND T_Status = 'Draft'";
            $delStmt = $pdo->prepare($delSql);
            $delStmt->execute([':id' => $t_id, ':requester_id' => $logged_in_emp_no]);
            header('Location: requests.php?tab=drafts');
            exit;
        } elseif ($_POST['action'] === 'send_draft') {
            $sendSql = "UPDATE requests SET T_Status = 'Pending', T_Updated_At = :updated_at WHERE T_Id = :id AND TRIM(T_Requester_Id) = TRIM(:requester_id)";
            $sendStmt = $pdo->prepare($sendSql);
            $sendStmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $t_id,
                ':requester_id' => $logged_in_emp_no
            ]);
            header('Location: requests.php?tab=new_request');
            exit;
        } elseif ($_POST['action'] === 'recall_request') {
            $recallSql = "UPDATE requests SET T_Status = 'Draft', T_Updated_At = :updated_at WHERE T_Id = :id AND TRIM(T_Requester_Id) = TRIM(:requester_id) AND T_Status = 'Pending'";
            $recallStmt = $pdo->prepare($recallSql);
            $recallStmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $t_id,
                ':requester_id' => $logged_in_emp_no
            ]);
            header('Location: requests.php?tab=drafts');
            exit;
        }
    }

    $countSql = "SELECT COUNT(*) FROM requests WHERE TRIM(T_Receiver_Id) = TRIM(:emp_no) AND T_Status = 'In Progress'";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':emp_no' => $logged_in_emp_no]);
    $in_progress_count = $countStmt->fetchColumn();

    if ($current_tab === 'new_request') {
        $sql = "SELECT * FROM requests WHERE TRIM(T_Requester_Id) = TRIM(:emp_no) AND T_Status = 'Pending' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':emp_no', $logged_in_emp_no, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $requests = $stmt->fetchAll();
    } elseif ($current_tab === 'drafts') {
        $sql = "SELECT * FROM requests WHERE TRIM(T_Requester_Id) = TRIM(:emp_no) AND T_Status = 'Draft' ORDER BY COALESCE(T_Updated_At, T_Created_At) DESC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':emp_no', $logged_in_emp_no, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $requests = $stmt->fetchAll();
   } elseif ($current_tab === 'history') {
        $sql = "SELECT * FROM requests WHERE TRIM(T_Requester_Id) = TRIM(:emp_no) AND T_Status != 'Draft'
                ORDER BY 
                    CASE 
                        WHEN T_Status = 'Pending' THEN 1 
                        WHEN T_Status = 'In Progress' THEN 2 
                        ELSE 3 
                    END ASC, 
                    COALESCE(T_Updated_At, T_Created_At) DESC 
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':emp_no', $logged_in_emp_no, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $requests = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}

$total_records = count($requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests Management</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="style_index.css">
    <style>
        .container { max-width: 100% !important; width: 100% !important; }
        .header-title-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .header-title-wrapper h3 {
            margin: 0;
        }
        .btn-add-request {
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-add-request:hover {
            background-color: #1557b0;
        }
        .nav-badge { margin-left: auto; background-color: #1a73e8; color: #fff; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; min-width: 20px; text-align: center; }
        .sidebar-menu li a.active .nav-badge { background-color: #041e49; color: #fff; }
        .bottom-pagination { display: flex; justify-content: flex-end; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e1e3e1; font-size: 14px; color: #555; }
        .page-size-selector select { padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; margin-left: 5px; outline: none; color: #333; }

        .action-group { display: flex; gap: 5px; align-items: center; }
        .btn-edit { background: #ffc107; color: #000; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete { background: #dc3545; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-delete:hover { background: #c82333; }
        .btn-send { background: #28a745; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-send:hover { background: #218838; }
        .btn-recall { background: #fd7e14; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-recall:hover { background: #e8690b; }
        .btn-show { background: #17a2b8; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-show:hover { background: #138496; }

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
        .modal-box h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #333; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; outline: none;
        }
        .form-group textarea { resize: vertical; height: 90px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .modal-buttons button { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-confirm { background: #1a73e8; color: white; }
        .btn-draft { background: #5f6368; color: white; }
        .btn-cancel { background: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; }

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
            <li><a href="requests.php" class="active"><span class="material-icons-round">note_add</span><span class="nav-text">Requests</span></a></li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li><a href="index.php?tab=pending"><span class="material-icons-round">mail</span><span class="nav-text">Inbox</span></a></li>
            <li>
                <a href="index.php?tab=approval">
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
            <a href="logout.php" title="Logout" style="display: flex; align-items: center; gap: 16px; padding: 12px 18px; border-radius: 99px; text-decoration: none; color: #d93025; font-size: 14px; font-weight: 500; white-space: nowrap;">
                <span class="material-icons-round" style="font-size: 20px; flex-shrink: 0;">logout</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-navbar">
            <h2>Requests</h2>
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
                <div class="gmail-tabs">
                    <a href="requests.php?tab=new_request&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'new_request') ? 'active' : ''; ?>">
                        <span class="material-icons-round">note_add</span> New Request
                    </a>
                    <a href="requests.php?tab=drafts&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'drafts') ? 'active' : ''; ?>">
                        <span class="material-icons-round">drafts</span> Drafts
                    </a>
                    <a href="requests.php?tab=history&limit=<?php echo $limit; ?>" class="gmail-tab-item <?php echo ($current_tab === 'history') ? 'active' : ''; ?>">
                        <span class="material-icons-round">history</span> History
                    </a>
                </div>

                <div class="header-title-wrapper">
                    <h3>
                        <?php 
                            if ($current_tab === 'new_request') echo 'New Requests Management';
                            elseif ($current_tab === 'drafts') echo 'Drafts Management';
                            else echo 'Request History';
                        ?>
                    </h3>
                    
                    <?php if ($current_tab === 'new_request'): ?>
                        <button type="button" class="btn-add-request" onclick="openRequestModal()">
                            <span class="material-icons-round" style="font-size: 18px;">add</span> New Request
                        </button>
                    <?php endif; ?>
                </div>

                <div id="sectionContent">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Category</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                                <?php if ($current_tab === 'new_request' || $current_tab === 'drafts'): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['T_Ticket_No'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Category'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['T_Title'] ?? ''); ?></td>
                                        <td><span style="color: #007bff; font-weight: 500;"><?php echo htmlspecialchars($row['T_Status'] ?? ''); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['T_Updated_At'] ?? $row['T_Created_At'] ?? ''); ?></td>
                                        
                                        <?php if ($current_tab === 'new_request'): ?>
                                            <td>
                                                <div class="action-group">
                                                    <button type="button" class="btn-show" onclick='openDetailModal(<?php echo json_encode($row); ?>)'>View</button>

                                                    <form action="requests.php?tab=new_request" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to recall this request? It will be moved to drafts.');">
                                                        <input type="hidden" name="action" value="recall_request">
                                                        <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                        <button type="submit" class="btn-recall">Recall</button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($current_tab === 'drafts'): ?>
                                            <td>
                                                <div class="action-group">
                                                    <button type="button" class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                                    
                                                    <form action="requests.php?tab=drafts" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this draft?');">
                                                        <input type="hidden" name="action" value="delete_draft">
                                                        <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                        <button type="submit" class="btn-delete">Delete</button>
                                                    </form>

                                                    <form action="requests.php?tab=drafts" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to submit this request?');">
                                                        <input type="hidden" name="action" value="send_draft">
                                                        <input type="hidden" name="T_Id" value="<?php echo htmlspecialchars($row['T_Id'] ?? ''); ?>">
                                                        <button type="submit" class="btn-send">Submit</button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($current_tab === 'new_request' || $current_tab === 'drafts') ? '6' : '5'; ?>" style="text-align: center; color: #666;">No data records available.</td>
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

    <!-- Create Request Modal -->
    <div class="modal-overlay" id="requestModal">
        <div class="modal-box">
            <h3>Create New Request</h3>
            <form action="requests.php?tab=new_request" method="POST" id="requestForm">
                <input type="hidden" name="action" value="create_request">
                <input type="hidden" name="form_action_type" id="form_action_type" value="submit">
                
                <div class="form-group">
                    <label for="T_Date">Application Date</label>
                    <input type="datetime-local" id="T_Date" name="T_Date" value="<?php echo date('Y-m-d\TH:i'); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="T_Category">Category</label>
                    <select id="T_Category" name="T_Category" required>
                        <option value="資訊相關">IT Related</option>
                        <option value="行政相關">Admin Related</option>
                        <option value="維修相關">Maintenance Related</option>
                        <option value="其他">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="T_Title">Subject (Title)</label>
                    <input type="text" id="T_Title" name="T_Title" placeholder="Please enter subject..." required>
                </div>

                <div class="form-group">
                    <label for="T_Description">Description</label>
                    <textarea id="T_Description" name="T_Description" placeholder="Please describe the reason in detail..." required></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeRequestModal()">Cancel</button>
                    <button type="button" class="btn-draft" onclick="submitFormAs('draft')">Save as Draft</button>
                    <button type="button" class="btn-confirm" onclick="submitFormAs('submit')">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Draft Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <h3>Edit Draft</h3>
            <form action="requests.php" method="POST" id="editForm">
                <input type="hidden" name="action" value="update_draft">
                <input type="hidden" name="T_Id" id="edit_T_Id">
                <input type="hidden" name="edit_action_type" id="edit_action_type" value="save">
                
                <div class="form-group">
                    <label for="edit_T_Date">Application Date</label>
                    <input type="datetime-local" id="edit_T_Date" name="T_Date" required>
                </div>

                <div class="form-group">
                    <label for="edit_T_Category">Category</label>
                    <select id="edit_T_Category" name="T_Category" required>
                        <option value="資訊相關">IT Related</option>
                        <option value="行政相關">Admin Related</option>
                        <option value="維修相關">Maintenance Related</option>
                        <option value="其他">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_T_Title">Subject (Title)</label>
                    <input type="text" id="edit_T_Title" name="T_Title" required>
                </div>

                <div class="form-group">
                    <label for="edit_T_Description">Description</label>
                    <textarea id="edit_T_Description" name="T_Description" required></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="button" class="btn-send" onclick="submitEditFormAs('send')">Submit</button>
                    <button type="button" class="btn-confirm" onclick="submitEditFormAs('save')">Save Changes</button>
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

    <script>
        function openRequestModal() {
            document.getElementById('requestModal').style.display = 'flex';
        }
        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
        }

        function openEditModal(row) {
            document.getElementById('edit_T_Id').value = row.T_Id;
            let rawDate = row.T_Created_At || row.T_Updated_At || '';
            if (rawDate) {
                document.getElementById('edit_T_Date').value = rawDate.replace(' ', 'T').substring(0, 16);
            }
            document.getElementById('edit_T_Category').value = (row.T_Category || '').trim();
            document.getElementById('edit_T_Title').value = row.T_Title || '';
            document.getElementById('edit_T_Description').value = row.T_Description || '';
            
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
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

        function submitFormAs(type) {
            document.getElementById('form_action_type').value = type;
            document.getElementById('requestForm').submit();
        }

        function submitEditFormAs(type) {
            document.getElementById('edit_action_type').value = type;
            document.getElementById('editForm').submit();
        }

        function changePageSize(size) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', size);
            if (!urlParams.has('tab')) {
                urlParams.set('tab', '<?php echo $current_tab; ?>');
            }
            window.location.href = 'requests.php?' + urlParams.toString();
        }
    </script>
</body>
</html>