// Sidebar collapse toggle
const toggleBtn = document.getElementById('toggleBtn');
const sidebar = document.getElementById('sidebar');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
});

// Focus search input when sidebar search button is clicked
function focusSearch() {
    const searchInput = document.getElementById('searchInput');
    searchInput.focus();
    if (sidebar.classList.contains('collapsed')) {
        sidebar.classList.remove('collapsed');
    }
}

// Tab switching logic
function switchTab(element, titleName) {
    document.querySelectorAll('.sidebar-menu a').forEach(item => {
        item.classList.remove('active');
    });
    element.classList.add('active');

    document.getElementById('pageTitle').innerText = titleName;
    document.getElementById('sectionTitle').innerText = `${titleName} Management & View`;
    document.getElementById('searchInput').value = ''; 
    
    // 取得「待接收/已接收/待指派」分頁籤容器
    const gmailTabs = document.querySelector('.gmail-tabs');
    
    if (gmailTabs) {
        if (titleName === 'Inbox') {
            gmailTabs.style.display = 'flex';
        } else {
            gmailTabs.style.display = 'none';
        }
    }

    // 當點擊側邊欄的 Pending Approval 時，直接導向帶有 tab=approval 的 PHP 頁面
    if (titleName === 'Pending Approval') {
        location.href = 'index.php?tab=approval';
        return;
    }

    const contentDiv = document.getElementById('sectionContent');
    if (titleName === 'Requests') {
        contentDiv.innerHTML = `
            <div class="form-group">
                <label>Request Subject</label>
                <input type="text" placeholder="Please enter subject...">
            </div>
            <div class="form-group">
                <label>Request Description</label>
                <textarea rows="4" placeholder="Please enter detailed content..."></textarea>
            </div>
            <button class="submit-btn">Submit Request</button>
        `;
    } else if (titleName !== 'Inbox') {
        contentDiv.innerHTML = `
            <p style="color: #666; font-size: 14px;">No data records available for "${titleName}".</p>
        `;
    } else {
        location.href = 'index.php?tab=pending';
    }
}

// Search input event handler
function handleSearch(keyword) {
    console.log("Searching for:", keyword);
}

