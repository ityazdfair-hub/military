
<?php
// admin/soldiers.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $full_name = sanitize($_POST['full_name']);
        $father_name = sanitize($_POST['father_name']);
        $national_id = sanitize($_POST['national_id']);
        $unit = sanitize($_POST['unit']);
        $leave_balance = intval($_POST['leave_balance']);
        $entry_time = sanitize($_POST['entry_time']);
        $exit_time = sanitize($_POST['exit_time']);
        $max_delay_minutes = intval($_POST['max_delay_minutes']);
        
        
        if ($action == 'add') {
            // Check if soldier already exists
            $stmt = $db->prepare("SELECT id FROM soldiers WHERE national_id = :national_id");
            $stmt->bindParam(':national_id', $national_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $message = 'سربازی با این کد ملی قبلاً ثبت شده است';
            } else {
                // Add new soldier
                $stmt = $db->prepare("INSERT INTO soldiers (full_name, father_name, national_id, unit, leave_balance, max_delay_minutes, entry_time, exit_time) 
                      VALUES (:full_name, :father_name, :national_id, :unit, :leave_balance, :max_delay_minutes, :entry_time, :exit_time)");
                $stmt->bindParam(':max_delay_minutes', $max_delay_minutes);

                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':father_name', $father_name);
                $stmt->bindParam(':national_id', $national_id);
                $stmt->bindParam(':unit', $unit);
                $stmt->bindParam(':leave_balance', $leave_balance);
                $stmt->bindParam(':entry_time', $entry_time);
                $stmt->bindParam(':exit_time', $exit_time);
                
                if ($stmt->execute()) {
                    $soldier_id = $db->lastInsertId();
                    
                    // Add approvers if set
                    if (!empty($_POST['approvers'])) {
                        $order = 1;
                        foreach ($_POST['approvers'] as $user_id) {
                            $stmt = $db->prepare("INSERT INTO soldier_approvers (soldier_id, user_id, approval_order) 
                                                 VALUES (:soldier_id, :user_id, :approval_order)");
                            $stmt->bindParam(':soldier_id', $soldier_id);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->bindParam(':approval_order', $order);
                            $stmt->execute();
                            $order++;
                        }
                    }
                    
                    $message = 'سرباز با موفقیت اضافه شد';
                    header('Location: soldiers.php?message=' . urlencode($message));
                    exit;
                } else {
                    $message = 'خطا در ثبت اطلاعات';
                }
            }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            
            // Update soldier
           $stmt = $db->prepare("UPDATE soldiers SET full_name = :full_name, father_name = :father_name, 
                     national_id = :national_id, unit = :unit, leave_balance = :leave_balance, 
                     max_delay_minutes = :max_delay_minutes, entry_time = :entry_time, exit_time = :exit_time WHERE id = :id");

            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':father_name', $father_name);
            $stmt->bindParam(':national_id', $national_id);
            $stmt->bindParam(':unit', $unit);
            $stmt->bindParam(':leave_balance', $leave_balance);
            $stmt->bindParam(':entry_time', $entry_time);
            $stmt->bindParam(':exit_time', $exit_time);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':max_delay_minutes', $max_delay_minutes);

            if ($stmt->execute()) {
                // Clear existing approvers
                $stmt = $db->prepare("DELETE FROM soldier_approvers WHERE soldier_id = :soldier_id");
                $stmt->bindParam(':soldier_id', $id);
                $stmt->execute();
                
                // Add new approvers
                if (!empty($_POST['approvers'])) {
                    $order = 1;
                    foreach ($_POST['approvers'] as $user_id) {
                        $stmt = $db->prepare("INSERT INTO soldier_approvers (soldier_id, user_id, approval_order) 
                                             VALUES (:soldier_id, :user_id, :approval_order)");
                        $stmt->bindParam(':soldier_id', $id);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':approval_order', $order);
                        $stmt->execute();
                        $order++;
                    }
                }
                
                $message = 'اطلاعات سرباز با موفقیت بروزرسانی شد';
                header('Location: soldiers.php?message=' . urlencode($message));
                exit;
            } else {
                $message = 'خطا در بروزرسانی اطلاعات';
            }
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        
        // Delete soldier
        $stmt = $db->prepare("DELETE FROM soldiers WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $message = 'سرباز با موفقیت حذف شد';
            header('Location: soldiers.php?message=' . urlencode($message));
            exit;
        } else {
            $message = 'خطا در حذف سرباز';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'archive') {
    $id = intval($_POST['id']);
    $admin_id = $_SESSION['user_id'];
    
    // Archive soldier
    $stmt = $db->prepare("UPDATE soldiers SET is_archived = 1, archived_at = NOW(), archived_by = :archived_by WHERE id = :id");
    $stmt->bindParam(':archived_by', $admin_id);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $message = 'سرباز با موفقیت به بایگانی منتقل شد';
        header('Location: soldiers.php?message=' . urlencode($message));
        exit;
    } else {
        $message = 'خطا در انتقال به بایگانی';
    }
}


// Get message from URL
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}

// Get all soldiers
$stmt = $db->query("SELECT * FROM soldiers WHERE is_archived = 0 ORDER BY full_name");

$soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for approver selection
$stmt = $db->query("SELECT id, full_name, unit FROM users ORDER BY full_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get soldier data for edit
$soldier = null;
$soldier_approvers = [];
if ($action == 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM soldiers WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $soldier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($soldier) {
        $stmt = $db->prepare("SELECT user_id FROM soldier_approvers WHERE soldier_id = :soldier_id ORDER BY approval_order");
        $stmt->bindParam(':soldier_id', $id);
        $stmt->execute();
        $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($approvers as $approver) {
            $soldier_approvers[] = $approver['user_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سربازان | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>مدیریت سربازان</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($action == 'add' || ($action == 'edit' && $soldier)): ?>
        <div class="card">
            <h2><?php echo ($action == 'add') ? 'افزودن سرباز جدید' : 'ویرایش اطلاعات سرباز'; ?></h2>
            
            <form method="post" action="">
                <?php if ($action == 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $soldier['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>نام و نام خانوادگی:</label>
                    <input type="text" name="full_name" value="<?php echo ($soldier) ? $soldier['full_name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>نام پدر:</label>
                    <input type="text" name="father_name" value="<?php echo ($soldier) ? $soldier['father_name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>کد ملی:</label>
                    <input type="text" name="national_id" value="<?php echo ($soldier) ? $soldier['national_id'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>واحد شغلی:</label>
                    <input type="text" name="unit" value="<?php echo ($soldier) ? $soldier['unit'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>میزان مرخصی مجاز (ساعت):</label>
                    <input type="number" name="leave_balance" value="<?php echo ($soldier) ? $soldier['leave_balance'] : '0'; ?>" required>
                </div>
                <div class="form-group">
    <label>حداکثر تاخیر مجاز (دقیقه):</label>
    <input type="number" name="max_delay_minutes" value="<?php echo ($soldier) ? $soldier['max_delay_minutes'] : '120'; ?>" required min="1">
</div>
                
                <div class="form-group">
    <label>ساعت ورود:</label>
    <input type="text" id="entry_time_display" class="time-input" readonly value="<?php echo ($soldier) ? substr($soldier['entry_time'], 0, 5) : '08:00'; ?>">
    <input type="hidden" name="entry_time" id="entry_time" value="<?php echo ($soldier) ? $soldier['entry_time'] : '08:00:00'; ?>">
</div>

<div class="form-group">
    <label>ساعت خروج:</label>
    <input type="text" id="exit_time_display" class="time-input" readonly value="<?php echo ($soldier) ? substr($soldier['exit_time'], 0, 5) : '16:00'; ?>">
    <input type="hidden" name="exit_time" id="exit_time" value="<?php echo ($soldier) ? $soldier['exit_time'] : '16:00:00'; ?>">
</div>
                
                <div class="form-group">
    <label>کاربران تایید کننده خروج (به ترتیب اولویت):</label>
    <div class="approver-selection">
        <div class="selected-approvers" id="selected-approvers">
            <?php if (count($soldier_approvers) > 0): ?>
                <?php 
                // Get approver names
                $approver_names = [];
                foreach ($soldier_approvers as $approver_id) {
                    foreach ($users as $user) {
                        if ($user['id'] == $approver_id) {
                            $approver_names[$approver_id] = $user['full_name'] . ' (' . $user['unit'] . ')';
                            break;
                        }
                    }
                }
                ?>
                <?php foreach ($soldier_approvers as $index => $approver_id): ?>
                <div class="approver-tag" data-id="<?php echo $approver_id; ?>">
                    <span class="approver-order"><?php echo ($index + 1); ?>.</span>
                    <span class="approver-name"><?php echo $approver_names[$approver_id]; ?></span>
                    <span class="remove-approver" onclick="removeApprover(<?php echo $approver_id; ?>)">×</span>
                    <input type="hidden" name="approvers[]" value="<?php echo $approver_id; ?>">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="approver-search">
            <input type="text" id="approver-search" placeholder="جستجوی کاربر...">
            <div class="approver-results" id="approver-results">
                <!-- Results will be displayed here via JS -->
            </div>
        </div>
    </div>
    <div class="approver-info">
        <small>کاربران را به ترتیب مراحل تایید انتخاب کنید. اولین کاربر، اولین تایید کننده خواهد بود.</small>
    </div>
</div>

<!-- Time Picker Modal -->
<div id="time-picker-modal" class="modal">
    <div class="modal-content time-picker-content">
        <span class="close" onclick="closeTimePicker()">&times;</span>
        <h2>انتخاب ساعت</h2>
        
        <div class="time-picker-container">
            <div class="time-picker-column">
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeHour(1)">&uarr;</button>
                </div>
                <div id="hour-display" class="time-value">08</div>
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeHour(-1)">&darr;</button>
                </div>
            </div>
            
            <div class="time-separator">:</div>
            
            <div class="time-picker-column">
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeMinute(1)">&uarr;</button>
                </div>
                <div id="minute-display" class="time-value">00</div>
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeMinute(-1)">&darr;</button>
                </div>
            </div>
        </div>
        
        <div class="time-picker-presets">
            <button type="button" class="time-preset" onclick="setPresetTime(8, 0)">۸:۰۰</button>
            <button type="button" class="time-preset" onclick="setPresetTime(12, 0)">۱۲:۰۰</button>
            <button type="button" class="time-preset" onclick="setPresetTime(16, 0)">۱۶:۰۰</button>
            <button type="button" class="time-preset" onclick="setPresetTime(20, 0)">۲۰:۰۰</button>
        </div>
        
        <div class="time-picker-footer">
            <button type="button" class="btn btn-primary" onclick="confirmTimeSelection()">تایید</button>
            <button type="button" class="btn" onclick="closeTimePicker()">بستن</button>
        </div>
    </div>
</div>

<!-- 4. Add this JavaScript to both pages before the closing </body> tag -->
<script>
// Time Picker functionality
let currentTimeTargetInput = null;
let currentTimeHour = 8;
let currentTimeMinute = 0;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to time inputs
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('click', function() {
            openTimePicker(this);
        });
    });
});

// Open time picker modal
function openTimePicker(inputElement) {
    currentTimeTargetInput = inputElement;
    
    // Parse current value if exists
    if (inputElement.value) {
        const timeParts = inputElement.value.split(':');
        if (timeParts.length >= 2) {
            currentTimeHour = parseInt(timeParts[0]);
            currentTimeMinute = parseInt(timeParts[1]);
        }
    }
    
    // Update displays
    updateTimeDisplay();
    
    // Show modal
    document.getElementById('time-picker-modal').style.display = 'block';
}

// Close time picker modal
function closeTimePicker() {
    document.getElementById('time-picker-modal').style.display = 'none';
}

// Update time display
function updateTimeDisplay() {
    document.getElementById('hour-display').textContent = currentTimeHour.toString().padStart(2, '0');
    document.getElementById('minute-display').textContent = currentTimeMinute.toString().padStart(2, '0');
}

// Change hour
function changeHour(delta) {
    currentTimeHour = (currentTimeHour + delta + 24) % 24;
    updateTimeDisplay();
}

// Change minute
function changeMinute(delta) {
    currentTimeMinute = (currentTimeMinute + delta + 60) % 60;
    updateTimeDisplay();
}

// Set preset time
function setPresetTime(hour, minute) {
    currentTimeHour = hour;
    currentTimeMinute = minute;
    updateTimeDisplay();
}

// Confirm time selection
function confirmTimeSelection() {
    // Format time
    const formattedTime = `${currentTimeHour.toString().padStart(2, '0')}:${currentTimeMinute.toString().padStart(2, '0')}`;
    
    // Set value in display input
    currentTimeTargetInput.value = formattedTime;
    
    // Set value in hidden input (with seconds)
    const hiddenInputId = currentTimeTargetInput.id.replace('_display', '');
    document.getElementById(hiddenInputId).value = `${formattedTime}:00`;
    
    // Close time picker
    closeTimePicker();
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('time-picker-modal');
    if (event.target == modal) {
        closeTimePicker();
    }
    
    // If date picker modal exists, handle that too
    const dateModal = document.getElementById('date-picker-modal');
    if (dateModal && event.target == dateModal) {
        closeDatePicker();
    }
}
</script>

<!-- 5. Add these styles to the page -->
<style>
/* Time Input Styles */
.time-input {
    cursor: pointer;
    background-color: #FAFAFA;
    padding-left: 2.5rem;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%233b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>');
    background-repeat: no-repeat;
    background-position: left 10px center;
}

/* Time Picker Modal Styles */
.time-picker-content {
    max-width: 300px;
    padding: 1.5rem;
}

.time-picker-container {
     direction: ltr !important;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 1.5rem 0;
}

.time-picker-column {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.time-picker-arrows {
    height: 40px;
    display: flex;
    align-items: center;
}

.time-arrow {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3b82f6;
    cursor: pointer;
    transition: all 0.2s;
}

.time-arrow:hover {
    transform: scale(1.2);
}

.time-value {
    font-size: 2.5rem;
    font-weight: bold;
    padding: 0.5rem;
    border-radius: 0.25rem;
    min-width: 70px;
    text-align: center;
}

.time-separator {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0 0.5rem;
    padding-top: 40px;
}

.time-picker-presets {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.time-preset {
    padding: 0.5rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.25rem;
    background: none;
    cursor: pointer;
    transition: all 0.2s;
}

.time-preset:hover {
    background-color: #f8fafc;
    border-color: #3b82f6;
}

.time-picker-footer {
    display: flex;
    justify-content: space-between;
}
</style>
<script>
// Approver selection functionality
const users = <?php echo json_encode($users); ?>;
const selectedApprovers = <?php echo json_encode($soldier_approvers); ?>;
let currentApprovers = [...selectedApprovers];

function displaySearchResults(query) {
    const resultsContainer = document.getElementById('approver-results');
    resultsContainer.innerHTML = '';
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    const filteredUsers = users.filter(user => {
        // Skip already selected users
        if (currentApprovers.includes(user.id)) return false;
        
        // Search in name and unit
        return user.full_name.indexOf(query) !== -1 || user.unit.indexOf(query) !== -1;
    });
    
    if (filteredUsers.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">کاربری یافت نشد</div>';
    } else {
        filteredUsers.forEach(user => {
            const userElement = document.createElement('div');
            userElement.className = 'approver-item';
            userElement.innerHTML = `${user.full_name} (${user.unit})`;
            userElement.onclick = function() {
                addApprover(user.id, user.full_name, user.unit);
            };
            resultsContainer.appendChild(userElement);
        });
    }
    
    resultsContainer.style.display = 'block';
}

function addApprover(id, name, unit) {
    // Add to the current approvers
    currentApprovers.push(id);
    
    // Create and add the tag
    const selectedContainer = document.getElementById('selected-approvers');
    const approverTag = document.createElement('div');
    approverTag.className = 'approver-tag';
    approverTag.setAttribute('data-id', id);
    
    approverTag.innerHTML = `
        <span class="approver-order">${currentApprovers.length}.</span>
        <span class="approver-name">${name} (${unit})</span>
        <span class="remove-approver" onclick="removeApprover(${id})">×</span>
        <input type="hidden" name="approvers[]" value="${id}">
    `;
    
    selectedContainer.appendChild(approverTag);
    
    // Clear search
    document.getElementById('approver-search').value = '';
    document.getElementById('approver-results').style.display = 'none';
}

function removeApprover(id) {
    // Remove from current approvers
    const index = currentApprovers.indexOf(id);
    if (index !== -1) {
        currentApprovers.splice(index, 1);
    }
    
    // Remove the tag
    const tag = document.querySelector(`.approver-tag[data-id="${id}"]`);
    if (tag) {
        tag.remove();
    }
    
    // Reorder the remaining tags
    const tags = document.querySelectorAll('.approver-tag');
    tags.forEach((tag, idx) => {
        tag.querySelector('.approver-order').textContent = `${idx + 1}.`;
    });
}

// Add event listener for search input
document.getElementById('approver-search').addEventListener('input', function(e) {
    displaySearchResults(e.target.value);
});

// Close results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.approver-search')) {
        document.getElementById('approver-results').style.display = 'none';
    }
});
</script>

                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="soldiers.php" class="btn">انصراف</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="actions">
            <a href="?action=add" class="btn btn-primary">افزودن سرباز جدید</a>
        </div>
        <input type="text" id="search-soldiers" placeholder="جستجو در جدول سربازان..." class="search-input">

        <div class="table-container">
            <table id="soldiers-table">
                <thead>
                    <tr>
                        <th>نام و نام خانوادگی</th>
                        <th>نام پدر</th>
                        <th>کد ملی</th>
                        <th>واحد شغلی</th>
                        <th>مرخصی مجاز</th>
                        <th>ساعت ورود</th>
                        <th>ساعت خروج</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($soldiers) > 0): ?>
                        <?php foreach ($soldiers as $soldier): ?>
                        <tr>
                            <td><?php echo $soldier['full_name']; ?></td>
                            <td><?php echo $soldier['father_name']; ?></td>
                            <td><?php echo $soldier['national_id']; ?></td>
                            <td><?php echo $soldier['unit']; ?></td>
                            <td><?php echo $soldier['leave_balance']; ?> ساعت</td>
                            <td><?php echo $soldier['entry_time']; ?></td>
                            <td><?php echo $soldier['exit_time']; ?></td>
                            <td>
    <a href="?action=edit&id=<?php echo $soldier['id']; ?>" class="btn btn-sm">ویرایش</a>
    <form method="post" action="?action=delete" style="display: inline;" onsubmit="return confirm('آیا از حذف این سرباز اطمینان دارید؟');">
        <input type="hidden" name="id" value="<?php echo $soldier['id']; ?>">
        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
    </form>
    <form method="post" action="?action=archive" style="display: inline;" onsubmit="return confirm('آیا از انتقال این سرباز به بایگانی اطمینان دارید؟');">
        <input type="hidden" name="id" value="<?php echo $soldier['id']; ?>">
        <button type="submit" class="btn btn-sm btn-warning">بایگانی</button>
    </form>
</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">هیچ سربازی ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <script>
function setupTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll("tbody tr");

    input.addEventListener("input", function () {
        const filter = this.value.toLowerCase();
        rows.forEach(row => {
            const cells = Array.from(row.getElementsByTagName("td"));
            const matched = cells.some(cell => cell.textContent.toLowerCase().includes(filter));
            row.style.display = matched ? "" : "none";
        });
    });
}

document.addEventListener("DOMContentLoaded", function () {
    setupTableSearch("search-soldiers", "soldiers-table");
});
</script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>