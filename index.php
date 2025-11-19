
<?php
// index.php
require_once 'config.php';
requireLogin();

// If guard is logged in, redirect to guard panel
if (isGuard()) {
    header('Location: ' . BASE_URL . '/guard/index.php');
    exit;
}

// If admin is logged in, redirect to admin panel
if (isAdmin()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get soldiers this user is responsible for (first approver)
$stmt = $db->prepare("SELECT s.* FROM soldiers s 
                    INNER JOIN soldier_approvers sa ON s.id = sa.soldier_id 
                    WHERE sa.user_id = :user_id AND sa.approval_order = 1
                    ORDER BY s.full_name");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests for this user to approve
$stmt = $db->prepare("SELECT er.id, er.exit_date, er.exit_time, er.expected_entry_date, er.expected_entry_time, 
                           s.full_name as soldier_name, s.unit as soldier_unit, u.full_name as requester_name,
                           sa.approval_order
                      FROM exit_requests er
                      INNER JOIN soldiers s ON er.soldier_id = s.id
                      INNER JOIN users u ON er.created_by = u.id
                      INNER JOIN soldier_approvers sa ON er.soldier_id = sa.soldier_id
                      WHERE sa.user_id = :user_id 
                      AND er.current_approval_step = sa.approval_order
                      AND er.status = 'pending'
                      ORDER BY er.exit_date, er.exit_time");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$pending_approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process exit request submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_request'])) {
    $soldier_id = intval($_POST['soldier_id']);
    $exit_date = sanitize($_POST['exit_date']);
    $exit_time = sanitize($_POST['exit_time']);
    $expected_entry_date = sanitize($_POST['expected_entry_date']);
    $expected_entry_time = sanitize($_POST['expected_entry_time']);
    
    // Check if soldier is suspended
    $stmt = $db->prepare("SELECT COUNT(*) FROM exit_suspensions WHERE soldier_id = :soldier_id AND is_active = 1");
    $stmt->bindParam(':soldier_id', $soldier_id);
    $stmt->execute();
    $is_suspended = $stmt->fetchColumn() > 0;
    
    if ($is_suspended) {
        $message = 'این سرباز در لیست لغو خروجی قرار دارد و امکان ثبت درخواست خروج ندارد.';
    } else {
        // Get soldier's default times and leave balance
        $stmt = $db->prepare("SELECT entry_time, exit_time, leave_balance FROM soldiers WHERE id = :id");
        $stmt->bindParam(':id', $soldier_id);
        $stmt->execute();
        $soldier_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate time difference
        $default_exit = strtotime($soldier_info['exit_time']);
        $requested_exit = strtotime($exit_time);
        $default_entry = strtotime($soldier_info['entry_time']);
        $requested_entry = strtotime($expected_entry_time);
        
        $exit_diff_minutes = abs($requested_exit - $default_exit) / 60;
        $entry_diff_minutes = abs($requested_entry - $default_entry) / 60;
        $total_diff_hours = ceil(($exit_diff_minutes + $entry_diff_minutes) / 60);
        if ($total_diff_hours > $soldier_info['leave_balance']) {
            $message = 'میزان مرخصی سرباز کافی نیست. تفاوت زمانی: ' . $total_diff_hours . ' ساعت، مرخصی موجود: ' . $soldier_info['leave_balance'] . ' ساعت';
        } else {
        
        
        
        
    // Insert exit request
    $stmt = $db->prepare("INSERT INTO exit_requests (soldier_id, exit_date, exit_time, expected_entry_date, expected_entry_time, created_by)
                         VALUES (:soldier_id, :exit_date, :exit_time, :expected_entry_date, :expected_entry_time, :created_by)");
    $stmt->bindParam(':soldier_id', $soldier_id);
    $stmt->bindParam(':exit_date', $exit_date);
    $stmt->bindParam(':exit_time', $exit_time);
    $stmt->bindParam(':expected_entry_date', $expected_entry_date);
    $stmt->bindParam(':expected_entry_time', $expected_entry_time);
    $stmt->bindParam(':created_by', $user_id);
    
    if ($stmt->execute()) {
        
        
        if ($total_diff_hours > 0) {
                    $new_balance = $soldier_info['leave_balance'] - $total_diff_hours;
                    $stmt = $db->prepare("UPDATE soldiers SET leave_balance = :new_balance WHERE id = :id");
                    $stmt->bindParam(':new_balance', $new_balance);
                    $stmt->bindParam(':id', $soldier_id);
                    $stmt->execute();
        }
        $request_id = $db->lastInsertId();
        
        // Create initial approval record
        $stmt = $db->prepare("INSERT INTO approvals (exit_request_id, user_id, approval_step, status, approved_at)
                             VALUES (:exit_request_id, :user_id, 1, 'approved', NOW())");
        $stmt->bindParam(':exit_request_id', $request_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Check if there are more approvers
        $stmt = $db->prepare("SELECT COUNT(*) FROM soldier_approvers WHERE soldier_id = :soldier_id AND approval_order > 1");
        $stmt->bindParam(':soldier_id', $soldier_id);
        $stmt->execute();
        $more_approvers = ($stmt->fetchColumn() > 0);
        
        if ($more_approvers) {
            // Set current approval step to 2 (next approver)
            $stmt = $db->prepare("UPDATE exit_requests SET current_approval_step = 2 WHERE id = :id");
            $stmt->bindParam(':id', $request_id);
            $stmt->execute();
        } else {
            // No more approvers, set to final guard approval
            $max_order = 2; // Guard approval is always the last step
            $stmt = $db->prepare("UPDATE exit_requests SET current_approval_step = :max_order WHERE id = :id");
            $stmt->bindParam(':id', $request_id);
            $stmt->bindParam(':max_order', $max_order);
            $stmt->execute();
        }
        
        $message = 'درخواست خروج با موفقیت ثبت شد';
    } else {
        $message = 'خطا در ثبت درخواست';
    }
}}}

// Process approval action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_request'])) {
    $request_id = intval($_POST['request_id']);
    $approval_step = intval($_POST['approval_step']);
    $action = sanitize($_POST['action']);
    $notes = sanitize($_POST['notes']);
    
    // Update approval status
    $status = ($action == 'approve') ? 'approved' : 'denied';
    $stmt = $db->prepare("INSERT INTO approvals (exit_request_id, user_id, approval_step, status, approved_at, notes)
                         VALUES (:exit_request_id, :user_id, :approval_step, :status, NOW(), :notes)");
    $stmt->bindParam(':exit_request_id', $request_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':approval_step', $approval_step);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->execute();
    
    if ($action == 'approve') {
        // Get soldier ID for this request
        $stmt = $db->prepare("SELECT soldier_id FROM exit_requests WHERE id = :id");
        $stmt->bindParam(':id', $request_id);
        $stmt->execute();
        $soldier_id = $stmt->fetchColumn();
        
        // Check if there are more approvers
        $stmt = $db->prepare("SELECT COUNT(*) FROM soldier_approvers WHERE soldier_id = :soldier_id AND approval_order > :current_order");
        $stmt->bindParam(':soldier_id', $soldier_id);
        $stmt->bindParam(':current_order', $approval_step);
        $stmt->execute();
        $more_approvers = ($stmt->fetchColumn() > 0);
        
        if ($more_approvers) {
            // Set current approval step to next approver
            $next_step = $approval_step + 1;
            $stmt = $db->prepare("UPDATE exit_requests SET current_approval_step = :next_step WHERE id = :id");
            $stmt->bindParam(':id', $request_id);
            $stmt->bindParam(':next_step', $next_step);
            $stmt->execute();
        } else {
            // No more approvers, set to final guard approval
            $max_order = $approval_step + 1; // Guard approval is always the last step
            $stmt = $db->prepare("UPDATE exit_requests SET current_approval_step = :max_order WHERE id = :id");
            $stmt->bindParam(':id', $request_id);
            $stmt->bindParam(':max_order', $max_order);
            $stmt->execute();
        }
        
        $message = 'درخواست با موفقیت تایید شد';
    } else {
        // Deny the request
        $stmt = $db->prepare("UPDATE exit_requests SET status = 'denied' WHERE id = :id");
        $stmt->bindParam(':id', $request_id);
        $stmt->execute();
        
        $message = 'درخواست رد شد';
    }
    
    // Redirect to refresh the page
    header('Location: ' . BASE_URL . '/index.php?message=' . urlencode($message));
    exit;
}

// Get message from URL
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل کاربری | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/user_sidebar.php'; ?>
    
    <div class="content">
        <h1>پنل کاربری</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>ثبت درخواست خروج</h2>
            
            <?php if (count($soldiers) > 0): ?>
            <form method="post" action="">
                <input type="hidden" name="create_request" value="1">
                
                <div class="form-group">
                    <label>انتخاب سرباز:</label>
                    <select name="soldier_id" required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($soldiers as $soldier): ?>
                        <option value="<?php echo $soldier['id']; ?>">
                            <?php echo $soldier['full_name']; ?> (<?php echo $soldier['unit']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
    <label>تاریخ خروج:</label>
    <input type="text" id="exit_date_display" class="date-input" readonly>
    <input type="hidden" name="exit_date" id="exit_date" value="<?php echo date('Y-m-d'); ?>">
</div>

<div class="form-group">
    <label>ساعت خروج:</label>
    <input type="text" id="exit_time_display" class="time-input" readonly value="08:00">
    <input type="hidden" name="exit_time" id="exit_time" value="08:00:00">
</div>

<div class="form-group">
    <label>تاریخ ورود:</label>
    <input type="text" id="expected_entry_date_display" class="date-input" readonly>
    <input type="hidden" name="expected_entry_date" id="expected_entry_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
</div>



<div class="form-group">
    <label>ساعت ورود:</label>
    <input type="text" id="expected_entry_time_display" class="time-input" readonly value="08:00">
    <input type="hidden" name="expected_entry_time" id="expected_entry_time" value="08:00:00">
</div>

                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">ثبت درخواست</button>
                </div>
            </form>
            <?php else: ?>
            <p>شما مسئول هیچ سربازی نیستید. لطفاً با مدیر سیستم تماس بگیرید.</p>
            <?php endif; ?>
        </div>
        
        <?php if (count($pending_approvals) > 0): ?>
        <div class="card">
            <h2>درخواست‌های نیازمند تایید شما</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام سرباز</th>
                            <th>واحد شغلی</th>
                            <th>تاریخ خروج</th>
                            <th>ساعت خروج</th>
                            <th>تاریخ ورود</th>
                            <th>ساعت ورود</th>
                            <th>ثبت کننده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_approvals as $request): ?>
                        <tr>
                            <td><?php echo $request['soldier_name']; ?></td>
                            <td><?php echo $request['soldier_unit']; ?></td>
                            <td><?php echo formatJalaliDate($request['exit_date']); ?></td>
                            <td><?php echo $request['exit_time']; ?></td>
                            <td><?php echo formatJalaliDate($request['expected_entry_date']); ?></td>
                            <td><?php echo $request['expected_entry_time']; ?></td>
                            <td><?php echo $request['requester_name']; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="showApprovalModal(<?php echo $request['id']; ?>, <?php echo $request['approval_order']; ?>, '<?php echo $request['soldier_name']; ?>')">بررسی</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div id="date-picker-modal" class="modal">
    <div class="modal-content date-picker-content">
        <span class="close" onclick="closeDatePicker()">&times;</span>
        <h2>انتخاب تاریخ</h2>
        
        <div class="date-picker-header">
            <button type="button" onclick="prevMonth()">&lt;</button>
            <div id="current-month-year"></div>
            <button type="button" onclick="nextMonth()">&gt;</button>
        </div>
        
        <div class="weekday-header">
            <div>ش</div>
            <div>ی</div>
            <div>د</div>
            <div>س</div>
            <div>چ</div>
            <div>پ</div>
            <div>ج</div>
        </div>
        
        <div id="days-grid" class="days-grid"></div>
        
        <div class="date-picker-footer">
            <button type="button" class="btn btn-primary" onclick="selectToday()">امروز</button>
            <button type="button" class="btn" onclick="closeDatePicker()">بستن</button>
        </div>
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
                <div id="hour-display" class="time-value" contenteditable="true">08</div>
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeHour(-1)">&darr;</button>
                </div>
            </div>
            
            <div class="time-separator">:</div>
            
            <div class="time-picker-column">
                <div class="time-picker-arrows">
                    <button type="button" class="time-arrow" onclick="changeMinute(1)">&uarr;</button>
                </div>
                <div id="minute-display" class="time-value" contenteditable="true">00</div>
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
// Hour field
const hourDisplay = document.getElementById('hour-display');
hourDisplay.addEventListener('blur', validateHourInput);
hourDisplay.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.blur();
    }
});

// Minute field
const minuteDisplay = document.getElementById('minute-display');
minuteDisplay.addEventListener('blur', validateMinuteInput);
minuteDisplay.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.blur();
    }
});

function validateHourInput() {
    let val = parseInt(hourDisplay.textContent.replace(/\D/g, ''));
    if (isNaN(val) || val < 0 || val > 23) {
        hourDisplay.textContent = currentTimeHour.toString().padStart(2, '0');
    } else {
        currentTimeHour = val;
        hourDisplay.textContent = val.toString().padStart(2, '0');
    }
}

function validateMinuteInput() {
    let val = parseInt(minuteDisplay.textContent.replace(/\D/g, ''));
    if (isNaN(val) || val < 0 || val > 59) {
        minuteDisplay.textContent = currentTimeMinute.toString().padStart(2, '0');
    } else {
        currentTimeMinute = val;
        minuteDisplay.textContent = val.toString().padStart(2, '0');
    }
}

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
    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeApprovalModal()">&times;</span>
            <h2>بررسی درخواست خروج</h2>
            <form method="post" action="" id="approvalForm">
                <input type="hidden" name="approve_request" value="1">
                <input type="hidden" name="request_id" id="request_id">
                <input type="hidden" name="approval_step" id="approval_step">
                
                <p>درخواست خروج برای سرباز: <span id="soldier_name"></span></p>
                
                <div class="form-group">
                    <label>توضیحات:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">تایید</button>
                    <button type="submit" name="action" value="deny" class="btn btn-danger">رد</button>
                    <button type="button" class="btn" onclick="closeApprovalModal()">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
<script src="assets/js/jquery-3.6.0.min.js"></script>




    <script>
    function showApprovalModal(requestId, approvalStep, soldierName) {
        document.getElementById('request_id').value = requestId;
        document.getElementById('approval_step').value = approvalStep;
        document.getElementById('soldier_name').textContent = soldierName;
        document.getElementById('approvalModal').style.display = 'block';
    }
    
    function closeApprovalModal() {
        document.getElementById('approvalModal').style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('approvalModal');
        if (event.target == modal) {
            closeApprovalModal();
        }
    }
    </script>
    <script>
// Simple Persian Date Picker
let currentTargetInput = null;
let currentDate = {
    year: 1403,
    month: 2,
    day: 13
};

// Persian calendar constants
const persianMonths = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 
    'مرداد', 'شهریور', 'مهر', 'آبان', 
    'آذر', 'دی', 'بهمن', 'اسفند'
];

// Days in each month of the Persian calendar
const persianMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial values for date inputs
    setInitialDates();
    
    // Add click event listeners to date inputs
    document.querySelectorAll('.date-input').forEach(input => {
        input.addEventListener('click', function() {
            openDatePicker(this);
        });
    });
});

// Set initial dates
function setInitialDates() {
    // Set exit date to today (13 Ordibehesht 1403)
    document.getElementById('exit_date_display').value = '13 اردیبهشت 1403';
    document.getElementById('exit_date').value = '2025-05-03';
    
    // Set entry date to tomorrow (14 Ordibehesht 1403)
    document.getElementById('expected_entry_date_display').value = '14 اردیبهشت 1403';
    document.getElementById('expected_entry_date').value = '2025-05-04';
}

// Open date picker modal
function openDatePicker(inputElement) {
    currentTargetInput = inputElement;
    
    // Parse current value if exists
    if (inputElement.value) {
        const dateParts = inputElement.value.split(' ');
        if (dateParts.length === 3) {
            currentDate.day = parseInt(dateParts[0]);
            currentDate.month = persianMonths.indexOf(dateParts[1]) + 1;
            currentDate.year = parseInt(dateParts[2]);
        }
    }
    
    // Render calendar
    renderCalendar();
    
    // Show modal
    document.getElementById('date-picker-modal').style.display = 'block';
}

// Close date picker modal
function closeDatePicker() {
    document.getElementById('date-picker-modal').style.display = 'none';
}

// Render calendar
function renderCalendar() {
    // Update header
    document.getElementById('current-month-year').textContent = 
        persianMonths[currentDate.month - 1] + ' ' + currentDate.year;
    
    const daysGrid = document.getElementById('days-grid');
    daysGrid.innerHTML = '';
    
    // Calculate first day of month
    // In Persian calendar, the week starts with Saturday (0)
    const firstDayOfMonth = 0; // Saturday for simplicity
    
    // Add empty cells for days before the first day of month
    for (let i = 0; i < firstDayOfMonth; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'day empty';
        daysGrid.appendChild(emptyCell);
    }
    
    // Get number of days in current month
    const daysInMonth = persianMonthDays[currentDate.month - 1];
    
    // Add cells for each day
    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'day';
        dayCell.textContent = day;
        
        // Highlight current date
        if (day === currentDate.day) {
            dayCell.classList.add('current');
        }
        
        // Highlight today
        if (currentDate.year === 1403 && currentDate.month === 2 && day === 13) {
            dayCell.classList.add('today');
        }
        
        // Add click handler
        dayCell.addEventListener('click', function() {
            selectDate(day);
        });
        
        daysGrid.appendChild(dayCell);
    }
}

// Select a date
function selectDate(day) {
    currentDate.day = day;
    
    // Format Persian date
    const persianDate = `${currentDate.day} ${persianMonths[currentDate.month - 1]} ${currentDate.year}`;
    
    // Set value in display input
    currentTargetInput.value = persianDate;
    
    // Set value in hidden input (convert to Gregorian)
    const hiddenInputId = currentTargetInput.id.replace('_display', '');
    
    // Simple conversion for demo - in real app would use proper conversion
    let gregorianDate;
    if (currentDate.year === 1403 && currentDate.month === 2) {
        // May 2025
        gregorianDate = `2025-05-${currentDate.day < 12 ? 20 + currentDate.day : currentDate.day - 11}`;
    } else {
        // Fallback
        gregorianDate = '2025-05-03';
    }
    
    document.getElementById(hiddenInputId).value = gregorianDate;
    
    // Close date picker
    closeDatePicker();
}

// Go to previous month
function prevMonth() {
    currentDate.month--;
    if (currentDate.month < 1) {
        currentDate.month = 12;
        currentDate.year--;
    }
    renderCalendar();
}

// Go to next month
function nextMonth() {
    currentDate.month++;
    if (currentDate.month > 12) {
        currentDate.month = 1;
        currentDate.year++;
    }
    renderCalendar();
}

// Select today
function selectToday() {
    currentDate = {
        year: 1403,
        month: 2,
        day: 13
    };
    selectDate(13);
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('date-picker-modal');
    if (event.target == modal) {
        closeDatePicker();
    }
}
</script>

<style>
/* Date Picker Styles */
.date-input {
    cursor: pointer;
    background-color: #FAFAFA;
    padding-left: 2.5rem;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%233b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>');
    background-repeat: no-repeat;
    background-position: left 10px center;
}

.date-picker-content {
    max-width: 350px;
    padding: 1.5rem;
}

.date-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.date-picker-header button {
    background: none;
    border: 1px solid #e2e8f0;
    border-radius: 0.25rem;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    font-size: 1.25rem;
}

.date-picker-header button:hover {
    background-color: #f8fafc;
}

#current-month-year {
    font-weight: bold;
    font-size: 1.1rem;
}

.weekday-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: #6b7280;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.day:hover {
    background-color: #e2e8f0;
}

.day.empty {
    cursor: default;
}

.day.current {
    background-color: #3b82f6;
    color: white;
}

.day.today {
    border: 2px solid #3b82f6;
    font-weight: bold;
}

.date-picker-footer {
    margin-top: 1rem;
    display: flex;
    justify-content: space-between;
}
</style>
</body>
</html>