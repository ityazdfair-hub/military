<?php
// admin/create_exit_request.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$message = '';

// Get soldier ID from URL
if (!isset($_GET['soldier_id'])) {
    header('Location: ' . BASE_URL . '/admin/soldiers.php');
    exit;
}

$soldier_id = intval($_GET['soldier_id']);

// Get soldier details
$stmt = $db->prepare("SELECT * FROM soldiers WHERE id = :id");
$stmt->bindParam(':id', $soldier_id);
$stmt->execute();
$soldier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$soldier) {
    header('Location: ' . BASE_URL . '/admin/soldiers.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exit_date = sanitize($_POST['exit_date']);
    $exit_time = sanitize($_POST['exit_time']);
    $expected_entry_date = sanitize($_POST['expected_entry_date']);
    $expected_entry_time = sanitize($_POST['expected_entry_time']);
    $admin_id = $_SESSION['user_id'];
    
    // Insert direct exit request (with all staff approvals bypassed)
    $stmt = $db->prepare("INSERT INTO exit_requests (
                            soldier_id, exit_date, exit_time, 
                            expected_entry_date, expected_entry_time, 
                            created_by, direct_admin_request, 
                            current_approval_step, status
                         ) VALUES (
                            :soldier_id, :exit_date, :exit_time, 
                            :expected_entry_date, :expected_entry_time, 
                            :created_by, 1, 
                            :current_approval_step, 'pending'
                         )");
    
    // Set current approval step to guard approval (last step)
    // Get the maximum approval step for this soldier (or default to 1 if none)
    $stmt_max = $db->prepare("SELECT COALESCE(MAX(approval_order), 0) + 1 AS guard_step 
                              FROM soldier_approvers 
                              WHERE soldier_id = :soldier_id");
    $stmt_max->bindParam(':soldier_id', $soldier_id);
    $stmt_max->execute();
    $guard_step = $stmt_max->fetchColumn();
    
    $stmt->bindParam(':soldier_id', $soldier_id);
    $stmt->bindParam(':exit_date', $exit_date);
    $stmt->bindParam(':exit_time', $exit_time);
    $stmt->bindParam(':expected_entry_date', $expected_entry_date);
    $stmt->bindParam(':expected_entry_time', $expected_entry_time);
    $stmt->bindParam(':created_by', $admin_id);
    $stmt->bindParam(':current_approval_step', $guard_step);
    
    if ($stmt->execute()) {
        $request_id = $db->lastInsertId();
        
        // Auto-approve all staff approval steps
        $stmt_approvers = $db->prepare("SELECT approval_order, user_id 
                                         FROM soldier_approvers 
                                         WHERE soldier_id = :soldier_id
                                         ORDER BY approval_order");
        $stmt_approvers->bindParam(':soldier_id', $soldier_id);
        $stmt_approvers->execute();
        $approvers = $stmt_approvers->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert approval records for all staff approvers
        foreach ($approvers as $approver) {
            $stmt_approve = $db->prepare("INSERT INTO approvals (
                                            exit_request_id, user_id, 
                                            approval_step, status, 
                                            approved_at, notes
                                          ) VALUES (
                                            :exit_request_id, :user_id, 
                                            :approval_step, 'approved', 
                                            NOW(), 'تایید توسط مدیر سیستم'
                                          )");
            $stmt_approve->bindParam(':exit_request_id', $request_id);
            $stmt_approve->bindParam(':user_id', $approver['user_id']);
            $stmt_approve->bindParam(':approval_step', $approver['approval_order']);
            $stmt_approve->execute();
        }
        
        $message = 'درخواست خروج با موفقیت ثبت شد. کلیه تاییدهای کارمندان انجام شده و منتظر تایید دژبان است.';
    } else {
        $message = 'خطا در ثبت درخواست';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت درخواست خروج | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>ثبت درخواست خروج</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>ثبت درخواست خروج برای <?php echo $soldier['full_name']; ?></h2>
            
            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>نام سرباز:</label>
                        <input type="text" value="<?php echo $soldier['full_name']; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد شغلی:</label>
                        <input type="text" value="<?php echo $soldier['unit']; ?>" readonly>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ خروج:</label>
                        <input type="text" id="exit_date_display" class="date-input" readonly>
                        <input type="hidden" name="exit_date" id="exit_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>ساعت خروج:</label>
                        <input type="text" id="exit_time_display" class="time-input" readonly value="<?php echo date('H:i'); ?>">
                        <input type="hidden" name="exit_time" id="exit_time" value="<?php echo date('H:i:00'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ ورود:</label>
                        <input type="text" id="expected_entry_date_display" class="date-input" readonly>
                        <input type="hidden" name="expected_entry_date" id="expected_entry_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>ساعت ورود:</label>
                        <input type="text" id="expected_entry_time_display" class="time-input" readonly value="<?php echo date('H:i'); ?>">
                        <input type="hidden" name="expected_entry_time" id="expected_entry_time" value="<?php echo date('H:i:00'); ?>">
                    </div>
                </div>
                
                <div class="admin-note">
                    <p class="note">توجه: با ثبت این درخواست، تمام مراحل تایید کارمندان به صورت خودکار انجام می‌شود و درخواست مستقیماً برای تایید دژبان ارسال می‌گردد.</p>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">ثبت درخواست</button>
                    <a href="<?php echo BASE_URL; ?>/admin/soldiers.php" class="btn">انصراف</a>
                </div>
            </form>
        </div>
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
        
        <div class="time-picker-container" style="direction:ltr;">
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
                <div id="minute-display" class="time-value"contenteditable="true">00</div>
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

<script>
// Persian Date/Time Picker Integration Script

/**
 * Core Date Conversion Functions
 */
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

function gregorianToJalali(gy, gm, gd) {
    // Handle different input formats
    if (typeof gy === 'object') {
        // If input is a Date object
        gd = gy.getDate();
        gm = gy.getMonth() + 1;
        gy = gy.getFullYear();
    }
    
    var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    var gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = (365 * gy) +
               (Math.floor((gy2 + 3) / 4)) -
               (Math.floor((gy2 + 99) / 100)) +
               (Math.floor((gy2 + 399) / 400)) -
               80 + gd + g_d_m[gm - 1];
    jy += 33 * (Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * (Math.floor(days / 1461));
    days %= 1461;
    jy += Math.floor((days - 1) / 365);
    
    if (days > 365) days = (days - 1) % 365;
    
    var jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    
    return [jy, jm, jd];
}

function jalaliToGregorian(jy, jm, jd) {
    // Handle different input formats
    if (typeof jy === 'object') {
        jd = jy[2];
        jm = jy[1];
        jy = jy[0];
    }
    
    var gy = (jy <= 979) ? 621 : 1600;
    jy -= (jy <= 979) ? 0 : 979;
    var days = (365 * jy) + 
               ((Math.floor(jy / 33)) * 8) + 
               (Math.floor(((jy % 33) + 3) / 4)) + 
               78 + jd + 
               ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * (Math.floor(days / 146097));
    days %= 146097;
    
    if (days > 36524) {
        gy += 100 * (Math.floor(--days / 36524));
        days %= 36524;
        if (days >= 365) days++;
    }
    
    gy += 4 * (Math.floor(days / 1461));
    days %= 1461;
    gy += Math.floor((days - 1) / 365);
    
    if (days > 365) days = (days - 1) % 365;
    
    var gd = days + 1;
    var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var gm;
    
    for (gm = 0; gm < 13; gm++) {
        var v = sal_a[gm];
        if (gd <= v) break;
        gd -= v;
    }
    
    return [gy, gm, gd];
}

/**
 * Persian Calendar Utility Functions
 */
function getPersianMonthName(month) {
    var persianMonths = [
        "فروردین", "اردیبهشت", "خرداد", "تیر", 
        "مرداد", "شهریور", "مهر", "آبان", 
        "آذر", "دی", "بهمن", "اسفند"
    ];
    return persianMonths[month - 1];
}

function getMonthNumber(monthName) {
    var persianMonths = [
        "فروردین", "اردیبهشت", "خرداد", "تیر", 
        "مرداد", "شهریور", "مهر", "آبان", 
        "آذر", "دی", "بهمن", "اسفند"
    ];
    return persianMonths.indexOf(monthName) + 1;
}

function formatJalaliDate(jalaliDate) {
    var jy = jalaliDate[0];
    var jm = jalaliDate[1];
    var jd = jalaliDate[2];
    
    return jd + " " + getPersianMonthName(jm) + " " + jy;
}

function formatGregorianDate(date) {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getDaysInJalaliMonth(year, month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    if (isJalaliLeapYear(year)) return 30;
    return 29;
}

function isJalaliLeapYear(year) {
    return ((year % 33 % 4) - 1) === (parseInt((year % 33) / 4));
}

/**
 * Date Picker Implementation
 */
let currentTargetInput = null;
let currentDate = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial dates
    setInitialDates();
    
    // Add click event listeners to date inputs
    document.querySelectorAll('.date-input').forEach(input => {
        input.addEventListener('click', function() {
            openDatePicker(this);
        });
    });
    
    // Add click event listeners to time inputs
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('click', function() {
            openTimePicker(this);
        });
    });
});

function setInitialDates() {
    // Get today's date in Jalali
    const today = new Date();
    const jalaliToday = gregorianToJalali(today);
    
    // Get tomorrow's date
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const jalaliTomorrow = gregorianToJalali(tomorrow);
    
    // Set exit date (today)
    const exitDateDisplay = document.getElementById('exit_date_display');
    const exitDate = document.getElementById('exit_date');
    if (exitDateDisplay && exitDate) {
        exitDateDisplay.value = formatJalaliDate(jalaliToday);
        exitDate.value = formatGregorianDate(today);
    }
    
    // Set entry date (tomorrow)
    const entryDateDisplay = document.getElementById('expected_entry_date_display');
    const entryDate = document.getElementById('expected_entry_date');
    if (entryDateDisplay && entryDate) {
        entryDateDisplay.value = formatJalaliDate(jalaliTomorrow);
        entryDate.value = formatGregorianDate(tomorrow);
    }
    
    // Initialize current date
    currentDate = {
        year: jalaliToday[0],
        month: jalaliToday[1],
        day: jalaliToday[2]
    };
}

function openDatePicker(inputElement) {
    currentTargetInput = inputElement;
    
    // Parse current value if exists
    if (inputElement.value) {
        const dateParts = inputElement.value.split(' ');
        if (dateParts.length === 3) {
            currentDate = {
                day: parseInt(dateParts[0]),
                month: getMonthNumber(dateParts[1]),
                year: parseInt(dateParts[2])
            };
        }
    }
    
    // Render calendar
    renderCalendar();
    
    // Show modal
    document.getElementById('date-picker-modal').style.display = 'block';
}

function closeDatePicker() {
    document.getElementById('date-picker-modal').style.display = 'none';
}

function renderCalendar() {
    // Update header
    document.getElementById('current-month-year').textContent = 
        getPersianMonthName(currentDate.month) + ' ' + currentDate.year;
    
    const daysGrid = document.getElementById('days-grid');
    daysGrid.innerHTML = '';
    
    // Get first day of month (in Persian calendar)
    // Simplified version
    const firstDayOfMonth = 0; // Saturday
    
    // Add empty cells for days before the first day of month
    for (let i = 0; i < firstDayOfMonth; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'day empty';
        daysGrid.appendChild(emptyCell);
    }
    
    // Get days in current month
    const daysInMonth = getDaysInJalaliMonth(currentDate.year, currentDate.month);
    
    // Get today's date for highlighting
    const today = new Date();
    const jalaliToday = gregorianToJalali(today);
    
    // Add cells for each day
    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'day';
        dayCell.textContent = day;
        
        // Highlight today
        if (day === jalaliToday[2] && 
            currentDate.month === jalaliToday[1] && 
            currentDate.year === jalaliToday[0]) {
            dayCell.classList.add('today');
        }
        
        // Highlight selected date
        if (day === currentDate.day) {
            dayCell.classList.add('current');
        }
        
        // Add click handler
        dayCell.addEventListener('click', function() {
            selectDate(day);
        });
        
        daysGrid.appendChild(dayCell);
    }
}

function selectDate(day) {
    currentDate.day = day;
    
    // Create Jalali date array
    const jalaliDate = [currentDate.year, currentDate.month, currentDate.day];
    
    // Format Persian date for display
    const persianDate = formatJalaliDate(jalaliDate);
    
    // Set value in display input
    currentTargetInput.value = persianDate;
    
    // Set value in hidden input - convert to Gregorian
    const hiddenInputId = currentTargetInput.id.replace('_display', '');
    const gregDate = jalaliToGregorian(jalaliDate);
    // Format as YYYY-MM-DD
    const gregorianDate = `${gregDate[0]}-${gregDate[1].toString().padStart(2, '0')}-${gregDate[2].toString().padStart(2, '0')}`;
    document.getElementById(hiddenInputId).value = gregorianDate;
    
    // Close date picker
    closeDatePicker();
}

function prevMonth() {
    currentDate.month--;
    if (currentDate.month < 1) {
        currentDate.month = 12;
        currentDate.year--;
    }
    renderCalendar();
}

function nextMonth() {
    currentDate.month++;
    if (currentDate.month > 12) {
        currentDate.month = 1;
        currentDate.year++;
    }
    renderCalendar();
}

function selectToday() {
    const today = new Date();
    const jalaliToday = gregorianToJalali(today);
    
    currentDate = {
        year: jalaliToday[0],
        month: jalaliToday[1],
        day: jalaliToday[2]
    };
    
    selectDate(jalaliToday[2]);
}

/**
 * Time Picker Implementation
 */
let currentTimeTargetInput = null;
let currentTimeHour = 8;
let currentTimeMinute = 0;

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

function closeTimePicker() {
    document.getElementById('time-picker-modal').style.display = 'none';
}

function updateTimeDisplay() {
    document.getElementById('hour-display').textContent = currentTimeHour.toString().padStart(2, '0');
    document.getElementById('minute-display').textContent = currentTimeMinute.toString().padStart(2, '0');
}

function changeHour(delta) {
    currentTimeHour = (currentTimeHour + delta + 24) % 24;
    updateTimeDisplay();
}

function changeMinute(delta) {
    currentTimeMinute = (currentTimeMinute + delta + 60) % 60;
    updateTimeDisplay();
}

function setPresetTime(hour, minute) {
    currentTimeHour = hour;
    currentTimeMinute = minute;
    updateTimeDisplay();
}

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

// Close modals when clicking outside of them
window.addEventListener('click', function(event) {
    // Date picker modal
    const dateModal = document.getElementById('date-picker-modal');
    if (dateModal && event.target === dateModal) {
        closeDatePicker();
    }
    
    // Time picker modal
    const timeModal = document.getElementById('time-picker-modal');
    if (timeModal && event.target === timeModal) {
        closeTimePicker();
    }
});
</script>

<style>
/* Date Input Styles */
.date-input, .time-input {
    cursor: pointer;
    background-color: #FAFAFA;
    padding-left: 2.5rem;
    background-position: left 10px center;
    background-repeat: no-repeat;
}

.date-input {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%233b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>');
}

.time-input {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%233b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>');
}

/* Date Picker Modal Styles */
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

/* Time Picker Modal Styles */
.time-picker-content {
    max-width: 300px;
    padding: 1.5rem;
}

.time-picker-container {
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

<?php
// 2. Update admin/create_exit_request.php to correctly use the date and time pickers
// Remove the include and add this JavaScript instead:
?>

<script>
// This script is specific to the create_exit_request.php page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date and time pickers
    if (document.getElementById('exit_date_display') && 
        document.getElementById('expected_entry_date_display') &&
        document.getElementById('exit_time_display') &&
        document.getElementById('expected_entry_time_display')) {
        
        // Set current values
        setInitialDates();
    }
});
</script>
    <!-- Include Date/Time Picker Modals and Scripts -->
    <?php include '../includes/date_time_pickers.php'; ?>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>