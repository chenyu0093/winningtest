<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統管理介面</title>
    <!-- 引入 Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        /* 套用指定配色與基礎設定 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: #f2f2f2;
            color: #333333;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* 側邊欄樣式 */
        .sidebar {
            width: 260px;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e1e3e1;
            transition: width 0.3s ease, margin-left 0.3s ease;
            position: relative;
            z-index: 1000;
            padding: 12px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.02);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 8px;
            margin-bottom: 16px;
        }

        .toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #444746;
            transition: background-color 0.2s;
        }

        .toggle-btn:hover {
            background-color: #f0f4f9;
            color: #19306c;
        }

        /* 導覽選單 */
        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 16px;
            border-radius: 28px;
            text-decoration: none;
            color: #444746;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: background-color 0.2s, color 0.2s;
            cursor: pointer;
        }

        .sidebar-menu li a:hover {
            background-color: #f0f4f9;
            color: #19306c;
        }

        .sidebar-menu li a.active {
            background-color: #e8edfb;
            color: #19306c;
            font-weight: 600;
        }

        .sidebar-menu li a .material-icons-round {
            font-size: 20px;
            flex-shrink: 0;
        }

        .sidebar.collapsed .nav-text {
            display: none;
        }

        /* 主畫面區塊 */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background: #f2f2f2;
        }

        /* 頂部導覽列（含標題與搜尋列） */
        .top-navbar {
            background: #ffffff;
            padding: 15px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .top-navbar h2 {
            font-size: 20px;
            font-weight: 700;
            color: #19306c;
        }

        /* 搜尋列樣式 */
        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid #dcdfe6;
            border-radius: 8px;
            font-size: 14px;
            color: #333333;
            background-color: #f8f9fa;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            background-color: #ffffff;
            border-color: #19306c;
            box-shadow: 0 0 0 3px rgba(25, 48, 108, 0.1);
        }

        .search-box .material-icons-round {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #666666;
            font-size: 20px;
        }

        /* 內容容器 */
        .content-body {
            padding: 24px;
        }

        .container {
            max-width: 900px;
            background: #ffffff;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .container h3 {
            color: #19306c;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333333;
            font-size: 14px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dcdfe6;
            border-radius: 8px;
            font-size: 15px;
            color: #333333;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #19306c;
            box-shadow: 0 0 0 3px rgba(25, 48, 108, 0.1);
        }

        .submit-btn {
            background: #19306c;
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .submit-btn:hover {
            background: #122248;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #e1e3e1;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            color: #19306c;
            font-weight: 600;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            color: #fff;
            font-size: 12px;
            font-weight: 500;
        }

        .bg-pending { background: #e0a800; }
        .bg-notice { background: #17a2b8; }
    </style>
</head>
<body>

    <!-- 側邊欄 -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="toggle-btn" id="toggleBtn" title="展開/收合選單">
                <span class="material-icons-round">menu</span>
            </button>
        </div>
        <ul class="sidebar-menu">
            <!-- 新增：側邊欄搜尋按鈕 -->
            <li>
                <a onclick="focusSearch()" title="搜尋">
                    <span class="material-icons-round">search</span>
                    <span class="nav-text">搜尋</span>
                </a>
            </li>
            <hr style="border: none; border-top: 1px solid #e1e3e1; margin: 8px 0;">
            <li>
                <a href="#" class="active" onclick="switchTab(this, '信箱')">
                    <span class="material-icons-round">mail</span>
                    <span class="nav-text">信箱</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, '申請')">
                    <span class="material-icons-round">note_add</span>
                    <span class="nav-text">申請</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, '待審批')">
                    <span class="material-icons-round">hourglass_top</span>
                    <span class="nav-text">待審批</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, '待驗收')">
                    <span class="material-icons-round">fact_check</span>
                    <span class="nav-text">待驗收</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="switchTab(this, '已結案')">
                    <span class="material-icons-round">task_alt</span>
                    <span class="nav-text">已結案</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- 主畫面 -->
    <main class="main-content">
        <!-- 頂部導覽列（含標題與搜尋列） -->
        <header class="top-navbar">
            <h2 id="pageTitle">信箱</h2>
            <div class="search-box">
                <span class="material-icons-round">search</span>
                <input type="text" id="searchInput" placeholder="搜尋資料..." oninput="handleSearch(this.value)">
            </div>
        </header>

        <!-- 內容主體 -->
        <div class="content-body">
            <div class="container">
                <h3 id="sectionTitle">信箱管理與檢視</h3>
                <div id="sectionContent">
                    <table>
                        <thead>
                            <tr>
                                <th>狀態</th>
                                <th>主旨</th>
                                <th>寄件者 / 單位</th>
                                <th>時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-notice">通知</span></td>
                                <td>系統維護公告：本週六進行伺服器升級</td>
                                <td>資訊部</td>
                                <td>2026-07-26</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 側邊欄收合控制
        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });

        // 點擊側邊欄的搜尋時，自動將畫面焦點移至頂部搜尋框
        function focusSearch() {
            const searchInput = document.getElementById('searchInput');
            searchInput.focus();
            // 如果側邊欄目前是收合狀態，點擊搜尋時可以自動把它展開方便觀看
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
            }
        }

        // 頁籤切換互動邏輯
        function switchTab(element, titleName) {
            document.querySelectorAll('.sidebar-menu a').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');

            document.getElementById('pageTitle').innerText = titleName;
            document.getElementById('sectionTitle').innerText = `${titleName}管理與檢視`;
            document.getElementById('searchInput').value = ''; 
            
            const contentDiv = document.getElementById('sectionContent');
            if (titleName === '申請') {
                contentDiv.innerHTML = `
                    <div class="form-group">
                        <label>申請主旨</label>
                        <input type="text" placeholder="請輸入主旨...">
                    </div>
                    <div class="form-group">
                        <label>申請說明</label>
                        <textarea rows="4" placeholder="請輸入詳細內容..."></textarea>
                    </div>
                    <button class="submit-btn">送出申請</button>
                `;
            } else {
                contentDiv.innerHTML = `
                    <p style="color: #666; font-size: 14px;">目前尚無「${titleName}」相關的資料紀錄。</p>
                `;
            }
        }

        // 搜尋列輸入事件
        function handleSearch(keyword) {
            console.log("正在搜尋：", keyword);
        }
    </script>
</body>
</html>