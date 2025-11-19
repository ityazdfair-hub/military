<?php
// admin/reports.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';

// تابع تبدیل تاریخ شمسی به میلادی (در صورت وجود فیلد)

// دریافت تاریخ‌های شمسی و تبدیل به میلادی
$from_date_jalali = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$to_date_jalali = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';

// اگر تاریخ‌ها خالی هستند، مقادیر پیش‌فرض را تنظیم کنید
if (empty($from_date_jalali)) {
    $from_date = date('Y-m-d', strtotime('-30 days'));
} else {
    $from_date = jalaliToGregorian($from_date_jalali);
}

if (empty($to_date_jalali)) {
    $to_date = date('Y-m-d');
} else {
    $to_date = jalaliToGregorian($to_date_jalali);
}

$soldier_id = isset($_GET['soldier_id']) ? intval($_GET['soldier_id']) : 0;

// Handle delete request
if (isset($_POST['delete_request']) && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    
    // Check if user has permission to delete (optional: add permission check here)
    $delete_stmt = $db->prepare("DELETE FROM exit_requests WHERE id = :id");
    $delete_stmt->bindValue(':id', $request_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "درخواست با موفقیت حذف شد.";
    } else {
        $error_message = "خطا در حذف درخواست.";
    }
}

// Get all soldiers for filter
$stmt = $db->query("SELECT id, full_name, unit FROM soldiers ORDER BY full_name");
$soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query based on filters
$params = [];
$query = "SELECT er.id, er.exit_date, er.exit_time, er.actual_exit_time, er.expected_entry_date, er.expected_entry_time, 
                 er.actual_entry_date, er.actual_entry_time, er.status, er.direct_admin_request, er.delay_minutes,
                 s.full_name as soldier_name, s.unit as soldier_unit, 
                 u.full_name as requester_name
          FROM exit_requests er
          INNER JOIN soldiers s ON er.soldier_id = s.id
          INNER JOIN users u ON er.created_by = u.id
          WHERE er.exit_date BETWEEN :from_date AND :to_date";

$params[':from_date'] = $from_date;
$params[':to_date'] = $to_date;

if ($soldier_id > 0) {
    $query .= " AND er.soldier_id = :soldier_id";
    $params[':soldier_id'] = $soldier_id;
}

if ($report_type == 'late') {
    $query .= " AND er.status = 'completed' AND 
               (er.actual_entry_date > er.expected_entry_date OR 
               (er.actual_entry_date = er.expected_entry_date AND er.actual_entry_time > er.expected_entry_time))";
} elseif ($report_type == 'approved') {
    $query .= " AND er.status = 'approved'";
} elseif ($report_type == 'denied') {
    $query .= " AND er.status = 'denied'";
} elseif ($report_type == 'completed') {
    $query .= " AND er.status = 'completed'";
}

$query .= " ORDER BY er.exit_date DESC, er.exit_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        /* Custom dropdown search styling */
        .custom-select-container {
            position: relative;
            width: 300%;
        }
        
        /* تغییرات در قسمت CSS */
.custom-select {
    appearance: none;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333;
    cursor: pointer;
    font-size: 14px;
    height: 50px;
    padding: 8px 35px 8px 15px;
    direction: rtl;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
        
        .custom-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76,175,80,0.3);
        }
        
        .select-arrow {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 12px;
        }
        
        .search-box {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .search-box.active {
            display: block;
        }
        
        .search-input {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            direction: rtl;
        }
        
        .option-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .option-item {
            padding: 8px 15px;
            cursor: pointer;
            direction: rtl;
        }
        
        .option-item:hover {
            background-color: #f5f5f5;
        }
        
        .option-item.selected {
            background-color: #4CAF50;
            color: white;
        }
        
        /* Date input styling */
        .jalali-date-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            direction: rtl;
            text-align: right;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        
        .delete-form {
            display: inline-block;
        }
        
        .btn-danger {
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>گزارشات</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>فیلترها</h2>
            
            <form method="get" action="" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>نوع گزارش:</label>
                        <select name="type">
                            <option value="all" <?php echo ($report_type == 'all') ? 'selected' : ''; ?>>همه</option>
                            <option value="late" <?php echo ($report_type == 'late') ? 'selected' : ''; ?>>تاخیر</option>
                            <option value="approved" <?php echo ($report_type == 'approved') ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="denied" <?php echo ($report_type == 'denied') ? 'selected' : ''; ?>>رد شده</option>
                            <option value="completed" <?php echo ($report_type == 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                        </select>
                    </div>
                    
                    <!-- تغییرات در قسمت HTML -->
<div class="form-group">
    <label>سرباز:</label>
    <div class="custom-select-container">
        <div class="custom-select">
            <span id="selected-soldier">همه</span>
            <span class="select-arrow">▼</span>
        </div>
        <input type="hidden" name="soldier_id" id="soldier-id-input" value="<?php echo $soldier_id; ?>">
        <div class="search-box" id="soldier-search-box">
            <input type="text" class="search-input" placeholder="جستجو سرباز...">
            <ul class="option-list">
                <li class="option-item" data-value="0">همه</li>
                <?php foreach ($soldiers as $soldier): ?>
                <li class="option-item <?php echo ($soldier_id == $soldier['id']) ? 'selected' : ''; ?>" 
                    data-value="<?php echo $soldier['id']; ?>">
                    <?php echo $soldier['full_name']; ?> (<?php echo $soldier['unit']; ?>)
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>از تاریخ:</label>
                        <input type="text" name="from_date" id="from_date" value="<?php echo $from_date_jalali; ?>" 
                               class="jalali-date-input" placeholder="1403/01/01">
                    </div>
                    
                    <div class="form-group">
                        <label>تا تاریخ:</label>
                        <input type="text" name="to_date" id="to_date" value="<?php echo $to_date_jalali; ?>" 
                               class="jalali-date-input" placeholder="1403/12/29">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="reports.php" class="btn">حذف فیلتر</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>نتایج گزارش</h2>
            <div class="actions mt-3">
                <button onclick="printReport()" class="btn btn-primary">چاپ گزارش</button>
            </div>
            <?php if (count($reports) > 0): ?>
            <input type="text" id="search-reports" placeholder="جستجو در گزارش‌ها..." class="search-input">

            <div class="table-container">
                <table id="reports-table">
                    <thead>
                        <tr>
                            <th>نام سرباز</th>
                            <th>واحد شغلی</th>
                            <th>تاریخ خروج</th>
                            <th>ساعت خروج</th>
                            <th>ساعت خروج واقعی</th>
                            <th>تاریخ ورود مورد انتظار</th>
                            <th>ساعت ورود مورد انتظار</th>
                            <th>تاریخ ورود واقعی</th>
                            <th>ساعت ورود واقعی</th>
                            <th>وضعیت</th>
                            <th>میزان تاخیر</th>
                            <th>ثبت کننده</th>
                            <th>جزئیات</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr class="<?php echo ($report_type == 'late' || 
                                           ($report['actual_entry_date'] && $report['actual_entry_time'] && 
                                            ($report['actual_entry_date'] > $report['expected_entry_date'] || 
                                             ($report['actual_entry_date'] == $report['expected_entry_date'] && 
                                              $report['actual_entry_time'] > $report['expected_entry_time']))))
                                           ? 'late-entry' : ''; ?>">
                            <td><?php echo $report['soldier_name']; ?></td>
                            <td><?php echo $report['soldier_unit']; ?></td>
                            <td><?php echo formatJalaliDate($report['exit_date']); ?></td>
                            <td><?php echo $report['exit_time']; ?></td>
                            <td><?php echo $report['actual_exit_time'] ? $report['actual_exit_time'] : '-'; ?></td>
                            <td><?php echo formatJalaliDate($report['expected_entry_date']); ?></td>
                            <td><?php echo $report['expected_entry_time']; ?></td>
                            <td><?php echo $report['actual_entry_date'] ? formatJalaliDate($report['actual_entry_date']) : '-'; ?></td>
                            <td><?php echo $report['actual_entry_time'] ? $report['actual_entry_time'] : '-'; ?></td>
                            <td>
                                <?php 
                                    switch ($report['status']) {
                                        case 'pending': echo 'در انتظار تایید'; break;
                                        case 'approved': echo 'تایید شده'; break;
                                        case 'denied': echo 'رد شده'; break;
                                        case 'completed': echo 'تکمیل شده'; break;
                                    }
                                ?>
                            </td>
                            <td class="delay-value"><?php echo formatDelayTime($report['delay_minutes']); ?></td>
                            <td><?php echo $report['requester_name']; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="showRequestDetails(<?php echo $report['id']; ?>)">جزئیات</button>
                            </td>
                            <td>
                                <form method="post" style="display: inline;" onsubmit="return confirm('آیا از حذف این درخواست مطمئن هستید؟');">
                                    <input type="hidden" name="request_id" value="<?php echo $report['id']; ?>">
                                    <button type="submit" name="delete_request" class="btn btn-danger btn-sm">حذف</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="actions mt-3" style="display:none;">
                <button onclick="printReport()" class="btn btn-primary">چاپ گزارش</button>
            </div>
            <?php else: ?>
            <p>هیچ گزارشی با فیلترهای انتخاب شده یافت نشد.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="requestDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRequestDetailsModal()">&times;</span>
            <h2>جزئیات درخواست</h2>
            <div id="request-details-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
    // تغییرات در قسمت JavaScript
// Custom searchable select functionality
document.addEventListener('DOMContentLoaded', function() {
    const customSelect = document.querySelector('.custom-select');
    const searchBox = document.getElementById('soldier-search-box');
    const searchInput = searchBox.querySelector('.search-input');
    const optionList = searchBox.querySelector('.option-list');
    const optionItems = optionList.querySelectorAll('.option-item');
    const selectedSoldier = document.getElementById('selected-soldier');
    const soldierIdInput = document.getElementById('soldier-id-input');
    
    // Set initial value if soldier is selected
    <?php if ($soldier_id > 0): ?>
        const selectedOption = optionList.querySelector('.option-item[data-value="<?php echo $soldier_id; ?>"]');
        if (selectedOption) {
            selectedSoldier.textContent = selectedOption.textContent;
            soldierIdInput.value = '<?php echo $soldier_id; ?>';
            selectedOption.classList.add('selected');
        }
    <?php endif; ?>
    
    // Toggle search box on select click
    customSelect.addEventListener('click', function(e) {
        e.stopPropagation();
        searchBox.classList.toggle('active');
        if (searchBox.classList.contains('active')) {
            searchInput.focus();
        }
    });
    
    // Hide search box when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-select-container')) {
            searchBox.classList.remove('active');
        }
    });
    
    // Filter options based on search input
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        optionItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Handle option selection
    optionItems.forEach(item => {
        item.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            const text = this.textContent;
            
            // Update hidden input and displayed text
            soldierIdInput.value = value;
            selectedSoldier.textContent = text;
            
            // Update visual feedback
            optionItems.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Hide search box
            searchBox.classList.remove('active');
        });
    });
});

// Function to toggle search box
function toggleSearchBox() {
    const searchBox = document.getElementById('soldier-search-box');
    searchBox.classList.toggle('active');
}
    
    // Simple date validation for Persian dates
    function validateJalaliDate(input) {
        const pattern = /^[0-9]{4}\/[0-9]{1,2}\/[0-9]{1,2}$/;
        
        input.addEventListener('input', function() {
            const value = this.value;
            if (value && !pattern.test(value)) {
                this.style.borderColor = '#d9534f';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
    }
    
    // Apply date validation to date inputs
    document.addEventListener('DOMContentLoaded', function() {
        validateJalaliDate(document.getElementById('from_date'));
        validateJalaliDate(document.getElementById('to_date'));
    });
    
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
        setupTableSearch("search-reports", "reports-table");
    });
    
    // Show request details
    function showRequestDetails(requestId) {
        // Fetch request details via AJAX
        fetch('../ajax/request_details.php?id=' + requestId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('request-details-content').innerHTML = data;
                document.getElementById('requestDetailsModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function closeRequestDetailsModal() {
        document.getElementById('requestDetailsModal').style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('requestDetailsModal');
        if (event.target == modal) {
            closeRequestDetailsModal();
        }
    }
    function printReport() {
        // ایجاد یک پنجره جدید برای چاپ با اندازه بزرگتر
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        printWindow.document.write('<html dir="rtl"><head>');
        printWindow.document.write('<meta charset="UTF-8">');
        printWindow.document.write('<title>گزارش اتوماسیون ورود و خروج سرباز</title>');
        
        // استایل‌های بهینه‌شده برای چاپ با تمام ستون‌ها (به جز ستون حذف)
        printWindow.document.write(`
            <style>
                @page {
                    size: landscape;  /* چاپ به صورت افقی برای نمایش تمام ستون‌ها */
                    margin: 0.5cm;   /* حاشیه کمتر برای فضای بیشتر */
                }
                
                body {
                    font-family: 'Tahoma', 'Arial', sans-serif;
                    direction: rtl;
                    padding: 5px;
                    font-size: 12px;  /* فونت کوچکتر برای نمایش بهتر */
                }
                
                .print-header {
                    text-align: center;
                    margin-bottom: 10px;
                }
                
                .print-header h1 {
                    font-size: 16px;
                    margin: 5px 0;
                }
                
                .print-date {
                    text-align: left;
                    margin-bottom: 10px;
                    font-size: 11px;
                }
                
                .filter-info {
                    margin-bottom: 10px;
                    padding: 5px;
                    background-color: #f5f5f5;
                    border-radius: 5px;
                    font-size: 11px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;  /* ثابت کردن عرض ستون‌ها */
                }
                
                th, td {
                    border: 1px solid #000;
                    padding: 5px 3px;  /* پدینگ کمتر */
                    text-align: center;
                    font-size: 10px;  /* فونت کوچکتر برای جدول */
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                
                th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                }
                
                .late-entry {
                    background-color: #ffeeee;
                }
                
                .footer {
                    text-align: center;
                    font-size: 9px;
                    margin-top: 10px;
                    color: #666;
                }
                
                .page-info {
                    position: absolute;
                    bottom: 5px;
                    right: 5px;
                    font-size: 8px;
                    color: #888;
                }
                
                /* مشابه با تصویر نمونه شما */
                .report-title {
                    color: #1a53ff;
                    font-size: 20px;
                    text-align: right;
                    margin: 10px 0;
                    font-weight: bold;
                }
                
                .card {
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 15px;
                    background-color: #fff;
                }
                
                .card h2 {
                    color: #1a53ff;
                    font-size: 16px;
                    margin-top: 0;
                    margin-bottom: 15px;
                    text-align: right;
                }
                
                /* بهینه‌سازی برای چاپ تمام ستون‌ها */
                table.full-width-table {
                    width: 100%;
                    max-width: 100%;
                    table-layout: fixed;
                }
                
                table.full-width-table th, 
                table.full-width-table td {
                    width: auto;
                    min-width: 0;
                    max-width: 100px;
                }
            </style>
        `);
        
        printWindow.document.write('</head><body>');
        
        // عنوان گزارش مشابه تصویر شما
        printWindow.document.write('<div class="report-title">گزارشات</div>');
        
        // کارت فیلترها
        printWindow.document.write('<div class="card">');
        printWindow.document.write('<h2>فیلترها</h2>');
        
        // اطلاعات فیلتر
        const reportType = document.querySelector('select[name="type"]');
        const soldierSelect = document.querySelector('select[name="soldier_id"]');
        const fromDate = document.querySelector('input[name="from_date"]');
        const toDate = document.querySelector('input[name="to_date"]');
        
        if (reportType || soldierSelect || fromDate || toDate) {
            printWindow.document.write('<div class="filter-info">');
            
            if (reportType && reportType.value) {
                const selectedOption = reportType.options[reportType.selectedIndex];
                printWindow.document.write(`<div>نوع گزارش: ${selectedOption.text}</div>`);
            }
            
            if (soldierSelect && soldierSelect.value && soldierSelect.value !== '0') {
                const selectedOption = soldierSelect.options[soldierSelect.selectedIndex];
                printWindow.document.write(`<div>سرباز: ${selectedOption.text}</div>`);
            }
            
            if (fromDate && fromDate.value) {
                printWindow.document.write(`<div>از تاریخ: ${fromDate.value}</div>`);
            }
            
            if (toDate && toDate.value) {
                printWindow.document.write(`<div>تا تاریخ: ${toDate.value}</div>`);
            }
            
            printWindow.document.write('</div>');
        }
        
        printWindow.document.write('</div>');
        
        // کارت نتایج گزارش
        printWindow.document.write('<div class="card">');
        printWindow.document.write('<h2>نتایج گزارش</h2>');
        
        // جستجو در گزارش‌ها (فقط برای نمایش مشابه تصویر)
        printWindow.document.write('<input type="text" placeholder="جستجو در گزارش‌ها..." style="width: 300px; float: left; margin-bottom: 10px; padding: 5px; visibility: hidden;">');
        
        // ایجاد جدول با تمام ستون‌ها (به جز حذف)
        printWindow.document.write('<table class="full-width-table">');
        
        // هدر جدول
        printWindow.document.write('<thead><tr>');
        
        // عناوین ستون‌ها
        const headerCells = document.querySelectorAll('#reports-table thead th');
        const headerNames = [];
        
        // ذخیره عناوین ستون‌ها به جز ستون‌های جزئیات و حذف
        for (let i = 0; i < headerCells.length - 2; i++) {
            headerNames.push(headerCells[i].textContent.trim());
        }
        
        // افزودن همه ستون‌ها به جز "جزئیات" و "حذف"
        for (let i = 0; i < headerNames.length; i++) {
            printWindow.document.write(`<th>${headerNames[i]}</th>`);
        }
        
        printWindow.document.write('</tr></thead>');
        
        // محتوای جدول
        printWindow.document.write('<tbody>');
        
        // کپی تمام ردیف‌های جدول
        const rows = document.querySelectorAll('#reports-table tbody tr');
        rows.forEach(row => {
            const isLateEntry = row.classList.contains('late-entry') ? 'class="late-entry"' : '';
            printWindow.document.write(`<tr ${isLateEntry}>`);
            
            // کپی تمام سلول‌های هر ردیف به جز 2 سلول آخر (دکمه جزئیات و حذف)
            const cells = row.querySelectorAll('td');
            for (let i = 0; i < cells.length - 2; i++) {
                const cellContent = cells[i].textContent.trim() || '-';
                printWindow.document.write(`<td>${cellContent}</td>`);
            }
            
            printWindow.document.write('</tr>');
        });
        
        printWindow.document.write('</tbody></table>');
        printWindow.document.write('</div>');
        
  
        
        // فوتر (آدرس صفحه)
        printWindow.document.write('<div class="footer">');
        printWindow.document.write(window.location.href);
        printWindow.document.write('</div>');
        
        // شماره صفحه
        printWindow.document.write('<div class="page-info">1/1</div>');
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        // اجرای دستور چاپ پس از بارگذاری کامل صفحه
        printWindow.onload = function() {
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
            }, 1000);
        };
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>