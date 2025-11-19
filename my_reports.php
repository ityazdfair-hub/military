<?php
// my_reports.php - New file for user reports
require_once 'config.php';
requireLogin();

if (isGuard()) {
    header('Location: ' . BASE_URL . '/guard/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : date('Y-m-d');

// Get soldiers this user is responsible for
$stmt = $db->prepare("SELECT DISTINCT s.id FROM soldiers s 
                       INNER JOIN soldier_approvers sa ON s.id = sa.soldier_id 
                       WHERE sa.user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$responsible_soldiers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query based on filters
$params = [];
if (empty($responsible_soldiers)) {
    // If no soldiers, return empty result
    $query = "SELECT er.id, er.exit_date, er.exit_time, er.actual_exit_time, er.expected_entry_date, er.expected_entry_time, 
                     er.actual_entry_date, er.actual_entry_time, er.status, er.delay_minutes,
                     s.full_name as soldier_name, s.unit as soldier_unit, 
                     u.full_name as requester_name
              FROM exit_requests er
              INNER JOIN soldiers s ON er.soldier_id = s.id
              INNER JOIN users u ON er.created_by = u.id
              WHERE 1=0"; // This will return no results
} else {
    $soldiers_placeholders = implode(',', array_fill(0, count($responsible_soldiers), '?'));
    $query = "SELECT er.id, er.exit_date, er.exit_time, er.actual_exit_time, er.expected_entry_date, er.expected_entry_time, 
                     er.actual_entry_date, er.actual_entry_time, er.status, er.delay_minutes,
                     s.full_name as soldier_name, s.unit as soldier_unit, 
                     u.full_name as requester_name
              FROM exit_requests er
              INNER JOIN soldiers s ON er.soldier_id = s.id
              INNER JOIN users u ON er.created_by = u.id
              WHERE er.exit_date BETWEEN ? AND ? AND er.soldier_id IN ($soldiers_placeholders)";
    
    $params = array_merge([$from_date, $to_date], $responsible_soldiers);
    
    if ($status_filter != 'all') {
        $query .= " AND er.status = ?";
        $params[] = $status_filter;
    }
}

$query .= " ORDER BY er.exit_date DESC, er.exit_time DESC";

$stmt = $db->prepare($query);
// execute() will be called whether params is empty or not
// For the 1=0 case, params will be empty.
$stmt->execute($params); 
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات من | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* For table search input */
        .search-input {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/user_sidebar.php'; ?>
    
    <div class="content">
        <h1>گزارشات سربازان تحت نظارت</h1>
        
        <div class="card">
            <h2>فیلترها</h2>
            
            <form method="get" action="" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>وضعیت:</label>
                        <select name="status">
                            <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>همه</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>در انتظار تایید</option>
                            <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="denied" <?php echo ($status_filter == 'denied') ? 'selected' : ''; ?>>رد شده</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                            <option value="suspended" <?php echo ($status_filter == 'suspended') ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>از تاریخ:</label>
                        <input type="text" id="from_date_display" class="date-input" readonly>
                        <input type="hidden" name="from_date" id="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>تا تاریخ:</label>
                        <input type="text" id="to_date_display" class="date-input" readonly>
                        <input type="hidden" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="my_reports.php" class="btn">حذف فیلتر</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>نتایج گزارش</h2>
            
            <div class="actions mt-3" style="margin-bottom: 15px;">
                <button onclick="printReport()" class="btn btn-primary">چاپ گزارش</button>
            </div>

            <?php if (count($reports) > 0): ?>
            <input type="text" id="search-my-reports" placeholder="جستجو در گزارش‌ها..." class="search-input">
            <div class="table-container">
                <table id="my-reports-table"> {/* Added ID here */}
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
                            <th>میزان تاخیر</th>
                            <th>وضعیت</th>
                            <th>ثبت کننده</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr class="<?php echo ($report['delay_minutes'] !== null && $report['delay_minutes'] > 0) ? 'late-entry' : ''; ?>">
                            <td><?php echo htmlspecialchars($report['soldier_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['soldier_unit']); ?></td>
                            <td><?php echo htmlspecialchars(formatJalaliDate($report['exit_date'])); ?></td>
                            <td><?php echo htmlspecialchars($report['exit_time']); ?></td>
                            <td><?php echo $report['actual_exit_time'] ? htmlspecialchars($report['actual_exit_time']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(formatJalaliDate($report['expected_entry_date'])); ?></td>
                            <td><?php echo htmlspecialchars($report['expected_entry_time']); ?></td>
                            <td><?php echo $report['actual_entry_date'] ? htmlspecialchars(formatJalaliDate($report['actual_entry_date'])) : '-'; ?></td>
                            <td><?php echo $report['actual_entry_time'] ? htmlspecialchars($report['actual_entry_time']) : '-'; ?></td>
                            <td class="delay-value"><?php echo htmlspecialchars(formatDelayTime($report['delay_minutes'])); ?></td>
                            <td>
                                <?php 
                                    switch ($report['status']) {
                                        case 'pending': echo '<span class="status-badge status-pending">در انتظار تایید</span>'; break;
                                        case 'approved': echo '<span class="status-badge status-approved">تایید شده</span>'; break;
                                        case 'denied': echo '<span class="status-badge status-denied">رد شده</span>'; break;
                                        case 'completed': 
                                            if ($report['delay_minutes'] !== null && $report['delay_minutes'] > 0) {
                                                echo '<span class="status-badge status-late">تکمیل شده (با تاخیر)</span>';
                                            } else {
                                                echo '<span class="status-badge status-completed">تکمیل شده</span>';
                                            }
                                            break;
                                        case 'suspended': echo '<span class="status-badge status-denied">لغو شده</span>'; break; // Assuming suspended uses denied style
                                        default: echo htmlspecialchars($report['status']);
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($report['requester_name']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="showRequestDetails(<?php echo $report['id']; ?>)">جزئیات</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                </div>
        </div>
    </div>
    
   
    <?php include 'includes/footer.php'; ?>
  

    <script>
    function setupTableSearch(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return; // Exit if elements not found
        const rows = table.querySelectorAll("tbody tr");

        input.addEventListener("input", function () {
            const filter = this.value.toLowerCase().trim();
            rows.forEach(row => {
                const cells = Array.from(row.getElementsByTagName("td"));
                const matched = cells.some(cell => cell.textContent.toLowerCase().includes(filter));
                row.style.display = matched ? "" : "none";
            });
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        setupTableSearch("search-my-reports", "my-reports-table");
    });

    function printReport() {
        // ... (full printReport function code as provided previously)
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        printWindow.document.write('<html dir="rtl"><head>');
        printWindow.document.write('<meta charset="UTF-8">');
        printWindow.document.write('<title>گزارش اتوماسیون ورود و خروج سرباز</title>');

        printWindow.document.write(`
            <style>
                @page {
                    size: landscape;
                    margin: 0.5cm;
                }
                body {
                    font-family: 'Tahoma', 'Arial', sans-serif;
                    direction: rtl;
                    padding: 5px;
                    font-size: 12px;
                }
                .print-header { text-align: center; margin-bottom: 10px; }
                .print-header h1 { font-size: 16px; margin: 5px 0; }
                .print-date { text-align: left; margin-bottom: 10px; font-size: 11px; }
                .filter-info { margin-bottom: 10px; padding: 5px; background-color: #f5f5f5; border-radius: 5px; font-size: 11px; }
                table { width: 100%; border-collapse: collapse; table-layout: auto; }
                th, td { border: 1px solid #000; padding: 4px 2px; text-align: center; font-size: 9px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .late-entry { background-color: #ffeeee !important; }
                .footer { text-align: center; font-size: 9px; margin-top: 10px; color: #666; }
                .page-info { position: fixed; bottom: 5px; right: 5px; font-size: 8px; color: #888; }
                .report-title { color: #1a53ff; font-size: 20px; text-align: right; margin: 10px 0; font-weight: bold; }
                .card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #fff; }
                .card h2 { color: #1a53ff; font-size: 16px; margin-top: 0; margin-bottom: 15px; text-align: right; }
            </style>
        `);

        printWindow.document.write('</head><body>');
        printWindow.document.write('<div class="report-title">گزارشات سربازان تحت نظارت</div>');
        printWindow.document.write('<div class="card">');
        printWindow.document.write('<h2>فیلترها</h2>');

        const statusFilterSelect = document.querySelector('select[name="status"]');
        const fromDateDisplayInput = document.getElementById('from_date_display');
        const toDateDisplayInput = document.getElementById('to_date_display');

        if (statusFilterSelect || fromDateDisplayInput || toDateDisplayInput) {
            printWindow.document.write('<div class="filter-info">');
            if (statusFilterSelect && statusFilterSelect.value !== 'all') {
                const selectedOption = statusFilterSelect.options[statusFilterSelect.selectedIndex];
                printWindow.document.write(`<div>وضعیت: \${selectedOption.text}</div>`);
            }
            if (fromDateDisplayInput && fromDateDisplayInput.value) {
                printWindow.document.write(`<div>از تاریخ: \${fromDateDisplayInput.value}</div>`);
            }
            if (toDateDisplayInput && toDateDisplayInput.value) {
                printWindow.document.write(`<div>تا تاریخ: \${toDateDisplayInput.value}</div>`);
            }
            printWindow.document.write('</div>');
        }
        printWindow.document.write('</div>');

        printWindow.document.write('<div class="card">');
        printWindow.document.write('<h2>نتایج گزارش</h2>');
        printWindow.document.write('<table>');

        const originalTable = document.getElementById('my-reports-table');
        if (originalTable) {
            const header = originalTable.querySelector('thead').cloneNode(true);
            const ths = header.querySelectorAll('th');
            if (ths.length > 0) ths[ths.length -1].remove(); // Remove last th (Details)
            printWindow.document.querySelector('table').appendChild(header);

            const tbody = printWindow.document.createElement('tbody');
            const originalRows = originalTable.querySelectorAll('tbody tr');
            const searchInputValue = document.getElementById('search-my-reports').value.toLowerCase().trim();

            originalRows.forEach(row => {
                let displayRow = true;
                if (searchInputValue) {
                    const cellsText = Array.from(row.getElementsByTagName("td")).map(td => td.textContent.toLowerCase()).join(' ');
                    if (!cellsText.includes(searchInputValue)) {
                        displayRow = false;
                    }
                }
                if (displayRow) {
                    const clonedRow = row.cloneNode(true);
                    const tds = clonedRow.querySelectorAll('td');
                    if (tds.length > 0) tds[tds.length - 1].remove(); // Remove last td (Details button)
                    tbody.appendChild(clonedRow);
                }
            });
            printWindow.document.querySelector('table').appendChild(tbody);
        }

        printWindow.document.write('</table></div>');
        printWindow.document.write('<div class="footer">');
        printWindow.document.write(window.location.href);
        printWindow.document.write('</div>');
        printWindow.document.write('<div class="page-info">1/1</div>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();

        printWindow.onload = function() {
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        };
    }

    function showRequestDetails(requestId) {
        fetch('ajax/request_details.php?id=' + requestId)
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

    window.onclick = function(event) {
        var modal = document.getElementById('requestDetailsModal');
        if (event.target == modal) {
            closeRequestDetailsModal();
        }
    }
    </script>
</body>
</html>