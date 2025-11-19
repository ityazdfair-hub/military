
<?php
// my_requests.php - NEW FILE

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

// Build query based on filters
$params = [];
$query = "SELECT er.id, er.exit_date, er.exit_time, er.expected_entry_date, er.expected_entry_time, 
                 er.actual_entry_date, er.actual_entry_time, er.status, 
                 s.full_name as soldier_name, s.unit as soldier_unit
          FROM exit_requests er
          INNER JOIN soldiers s ON er.soldier_id = s.id
          WHERE er.created_by = :user_id AND er.exit_date BETWEEN :from_date AND :to_date";

$params[':user_id'] = $user_id;
$params[':from_date'] = $from_date;
$params[':to_date'] = $to_date;

if ($status_filter != 'all') {
    $query .= " AND er.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY er.exit_date DESC, er.exit_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval details
function getApprovalDetails($db, $request_id) {
    $stmt = $db->prepare("
        SELECT a.id, a.status, a.approved_at, a.notes, a.approval_step,
               COALESCE(u.full_name, g.full_name) as approver_name
        FROM approvals a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN guards g ON a.guard_id = g.id
        WHERE a.exit_request_id = :request_id
        ORDER BY a.approval_step
    ");
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تاریخچه درخواست‌ها | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/user_sidebar.php'; ?>
    
    <div class="content">
        <h1>تاریخچه درخواست‌های من</h1>
        
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
                        </select>
                    </div>
                    
                    
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="my_requests.php" class="btn">حذف فیلتر</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>درخواست‌های من</h2>
            
            <?php if (count($requests) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام سرباز</th>
                            <th>واحد شغلی</th>
                            <th>تاریخ خروج</th>
                            <th>ساعت خروج</th>
                            <th>تاریخ ورود مورد انتظار</th>
                            <th>ساعت ورود مورد انتظار</th>
                            <th>وضعیت</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr class="<?php echo 
                                   ($request['status'] == 'completed' && 
                                    ($request['actual_entry_date'] > $request['expected_entry_date'] || 
                                     ($request['actual_entry_date'] == $request['expected_entry_date'] && 
                                      $request['actual_entry_time'] > $request['expected_entry_time'])))
                                   ? 'late-entry' : ''; ?>">
                            <td><?php echo $request['soldier_name']; ?></td>
                            <td><?php echo $request['soldier_unit']; ?></td>
                            <td><?php echo formatJalaliDate($request['exit_date']); ?></td>
                            <td><?php echo $request['exit_time']; ?></td>
                            <td><?php echo formatJalaliDate($request['expected_entry_date']); ?></td>
                            <td><?php echo $request['expected_entry_time']; ?></td>
                            <td>
                                <?php 
                                    switch ($request['status']) {
                                        case 'pending': echo '<span class="status-badge status-pending">در انتظار تایید</span>'; break;
                                        case 'approved': echo '<span class="status-badge status-approved">تایید شده</span>'; break;
                                        case 'denied': echo '<span class="status-badge status-denied">رد شده</span>'; break;
                                        case 'completed': 
                                            if ($request['actual_entry_date'] > $request['expected_entry_date'] || 
                                                ($request['actual_entry_date'] == $request['expected_entry_date'] && 
                                                 $request['actual_entry_time'] > $request['expected_entry_time'])) {
                                                echo '<span class="status-badge status-late">تکمیل شده (با تاخیر)</span>';
                                            } else {
                                                echo '<span class="status-badge status-completed">تکمیل شده</span>';
                                            }
                                            break;
                                    }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm" onclick="showRequestDetails(<?php echo $request['id']; ?>)">جزئیات</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>هیچ درخواستی با فیلترهای انتخاب شده یافت نشد.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div id="requestDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRequestDetailsModal()">&times;</span>
            <h2>جزئیات درخواست</h2>
            <div id="request-details-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
   
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
   
    
    // Helper function to convert Gregorian to Jalali
    function toJalaliDate(gregorianDate) {
        // This would normally use a library conversion
        // For now, we're using a placeholder
        // In production, use appropriate conversion
        const date = new Date(gregorianDate);
        // This assumes 'persianDate' library is available
        return new persianDate(date).format('YYYY/MM/DD');
    }
    
    // Helper function to convert Jalali to Gregorian
    function toGregorianDate(jalaliDate) {
        // This would normally use a library conversion
        // For now, we're using a placeholder
        // In production, use appropriate conversion
        const [jYear, jMonth, jDay] = jalaliDate.split('/');
        // This assumes 'persianDate' library is available
        const gregDate = new persianDate([parseInt(jYear), parseInt(jMonth), parseInt(jDay)]).toCalendar('gregorian').toLocale('en').format('YYYY-MM-DD');
        return gregDate;
    }
    
    // Show request details
    function showRequestDetails(requestId) {
        // Fetch request details via AJAX
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
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('requestDetailsModal');
        if (event.target == modal) {
            closeRequestDetailsModal();
        }
    }
    </script>
</body>
</html>