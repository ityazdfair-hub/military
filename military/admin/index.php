
<?php
// admin/index.php
require_once '../config.php';
requireAdmin();

// Dashboard statistics
$db = getDB();

// Count soldiers
$stmt = $db->query("SELECT COUNT(*) FROM soldiers");
$soldiers_count = $stmt->fetchColumn();

// Count users
$stmt = $db->query("SELECT COUNT(*) FROM users");
$users_count = $stmt->fetchColumn();

// Count guards
$stmt = $db->query("SELECT COUNT(*) FROM guards");
$guards_count = $stmt->fetchColumn();

// Count today's exit requests
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) FROM exit_requests WHERE exit_date = :today");
$stmt->bindParam(':today', $today);
$stmt->execute();
$today_exits = $stmt->fetchColumn();

// Count soldiers currently in the base (not approved for exit)
$stmt = $db->query("
    SELECT COUNT(DISTINCT s.id) 
    FROM soldiers s
    LEFT JOIN exit_requests er ON s.id = er.soldier_id AND er.status = 'approved' 
        AND er.actual_entry_date IS NULL
    WHERE er.id IS NULL OR er.status != 'approved'
");
$soldiers_in_base = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
/* Styles for soldiers in base modal */
.soldiers-in-base {
    max-height: 70vh;
    overflow-y: auto;
}

.soldiers-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.soldiers-list li {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
}

.soldiers-list li:nth-child(even) {
    background-color: #f8fafc;
}

.soldier-name {
    font-weight: bold;
    flex: 1;
}

.soldier-father {
    color: #6b7280;
    margin: 0 1rem;
}

.soldier-unit {
    color: #4b5563;
    background-color: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.85rem;
}

.loading {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.error {
    background-color: #fee2e2;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

.info {
    background-color: #dbeafe;
    color: #1e40af;
    padding: 1rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

#soldiers-in-base-card {
    cursor: pointer;
    transition: all 0.2s;
}

#soldiers-in-base-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Search styles */
.search-container {
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    background: white;
    padding: 1rem;
    z-index: 10;
    border-bottom: 1px solid #e2e8f0;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 1rem;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
    background-repeat: no-repeat;
    background-position: left 10px center;
    padding-left: 2.5rem;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}
</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>پنل مدیریت</h1>
        
        <div class="dashboard">
            <div class="dashboard-card">
                <h3>سربازان</h3>
                <div class="dashboard-value"><?php echo $soldiers_count; ?></div>
                <a href="soldiers.php" class="btn">مدیریت سربازان</a>
            </div>
            
            <div class="dashboard-card">
                <h3>کاربران</h3>
                <div class="dashboard-value"><?php echo $users_count; ?></div>
                <a href="users.php" class="btn">مدیریت کاربران</a>
            </div>
            
            <div class="dashboard-card">
                <h3>دژبان‌ها</h3>
                <div class="dashboard-value"><?php echo $guards_count; ?></div>
                <a href="guards.php" class="btn">مدیریت دژبان‌ها</a>
            </div>
            <div class="dashboard-card" id="soldiers-in-base-card">
    <h3>سربازان حاضر در پادگان</h3>
    <div class="dashboard-value"><?php echo $soldiers_in_base; ?></div>
    <button type="button" class="btn" onclick="showSoldiersInBase()">نمایش لیست</button>
</div>
            
        </div>
    </div>
    <!-- Soldiers In Base Modal -->
<div id="soldiersInBaseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeSoldiersModal()">&times;</span>
        <h2>سربازان حاضر در پادگان</h2>
        <div id="soldiers-list-container">
            <div class="loading">در حال بارگذاری...</div>
        </div>
    </div>
</div>
    <?php include '../includes/footer.php'; ?>
</body>
<script>
// Get the modal
var soldiersModal = document.getElementById("soldiersInBaseModal");

// Show soldiers in base
function showSoldiersInBase() {
    // Show modal
    soldiersModal.style.display = "block";
    
    // Load soldier list with AJAX
    fetch('../ajax/soldiers_in_base.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('soldiers-list-container').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('soldiers-list-container').innerHTML = 
                '<div class="error">خطا در بارگذاری اطلاعات</div>';
        });
}

// Close modal
function closeSoldiersModal() {
    soldiersModal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    if (event.target == soldiersModal) {
        closeSoldiersModal();
    }
}
function filterSoldiers() {
        var input, filter, ul, li, names, i, txtValue;
        input = document.getElementById("soldierSearchInput");
        filter = input.value.toUpperCase();
        ul = document.getElementById("soldiersList");
        li = ul.getElementsByTagName("li");
        
        for (i = 0; i < li.length; i++) {
            names = li[i].getElementsByClassName("soldier-name")[0];
            txtValue = names.textContent || names.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }
</script>
</html>
