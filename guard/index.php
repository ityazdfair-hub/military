
<?php
// guard/index.php
require_once '../config.php';
requireLogin();

if (!isGuard()) {
    header('Location: ' . BASE_URL . '/unauthorized.php');
    exit;
}

$db = getDB();
$guard_id = $_SESSION['guard_id'];
$message = '';

// Process exit approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_exit'])) {
    $request_id = intval($_POST['request_id']);
    $action = sanitize($_POST['action']);
    $notes = sanitize($_POST['notes']);
    
    // Get the current step
    $stmt = $db->prepare("SELECT current_approval_step FROM exit_requests WHERE id = :id");
    $stmt->bindParam(':id', $request_id);
    $stmt->execute();
    $current_step = $stmt->fetchColumn();
    
    // Update approval status
    $status = ($action == 'approve') ? 'approved' : 'denied';
    $stmt = $db->prepare("INSERT INTO approvals (exit_request_id, guard_id, approval_step, status, approved_at, notes)
                         VALUES (:exit_request_id, :guard_id, :approval_step, :status, NOW(), :notes)");
    $stmt->bindParam(':exit_request_id', $request_id);
    $stmt->bindParam(':guard_id', $guard_id);
    $stmt->bindParam(':approval_step', $current_step);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->execute();
    
    if ($action == 'approve') {
        // Record actual exit time when guard approves
        $actual_exit_time = date('H:i:s'); // Current time
        
        // Update exit request status and actual exit time
        $stmt = $db->prepare("UPDATE exit_requests 
                             SET status = 'approved', actual_exit_time = :actual_exit_time 
                             WHERE id = :id");
        $stmt->bindParam(':actual_exit_time', $actual_exit_time);
        $stmt->bindParam(':id', $request_id);
        $stmt->execute();
        
        $message = 'خروج با موفقیت تایید شد';
    } else {
        // Deny the request
        $stmt = $db->prepare("UPDATE exit_requests SET status = 'denied' WHERE id = :id");
        $stmt->bindParam(':id', $request_id);
        $stmt->execute();
        
        $message = 'خروج رد شد';
    }
}

// Process entry confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_entry'])) {
    $request_id = intval($_POST['request_id']);
    $actual_entry_time = date('H:i:s'); // Use current time
    $actual_entry_date = date('Y-m-d'); // Use current date
    
    // Get expected entry time and soldier info
    $stmt = $db->prepare("SELECT er.expected_entry_time, er.expected_entry_date, 
                                 s.id as soldier_id, s.max_delay_minutes
                         FROM exit_requests er
                         INNER JOIN soldiers s ON er.soldier_id = s.id
                         WHERE er.id = :id");
    $stmt->bindParam(':id', $request_id);
    $stmt->execute();
    $entry_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entry_info) {
        $message = 'درخواست مورد نظر یافت نشد.';
    } else {
        // Check if soldier is already suspended
        $stmt = $db->prepare("SELECT COUNT(*) FROM exit_suspensions 
                             WHERE soldier_id = :soldier_id AND is_active = 1");
        $stmt->bindParam(':soldier_id', $entry_info['soldier_id']);
        $stmt->execute();
        $is_suspended = $stmt->fetchColumn() > 0;
        
        if ($is_suspended) {
            $message = 'این سرباز در لیست لغو خروجی قرار دارد و امکان ثبت ورود ندارد.';
        } else {
            // Calculate delay in minutes
            $expected_time = strtotime($entry_info['expected_entry_date'] . ' ' . $entry_info['expected_entry_time']);
            $actual_time = strtotime($actual_entry_date . ' ' . $actual_entry_time);
            $delay_minutes = 0;
            
            if ($actual_time > $expected_time) {
                // Calculate delay in minutes
                $delay_seconds = $actual_time - $expected_time;
                $delay_minutes = ceil($delay_seconds / 60);
            }
            
            // Check if delay exceeds maximum allowed
            if ($delay_minutes > $entry_info['max_delay_minutes']) {
                // Suspend soldier for excessive delay
                $stmt = $db->prepare("INSERT INTO exit_suspensions (soldier_id, reason, suspended_by, notes)
                                     VALUES (:soldier_id, 'excessive_delay', :suspended_by, :notes)");
                $stmt->bindParam(':soldier_id', $entry_info['soldier_id']);
                $stmt->bindParam(':suspended_by', $guard_id);
                $notes = 'لغو خودکار به دلیل تاخیر بیش از حد مجاز (' . $delay_minutes . ' دقیقه از ' . $entry_info['max_delay_minutes'] . ' دقیقه مجاز)';
                $stmt->bindParam(':notes', $notes);
                $stmt->execute();
                
                // Update request status to suspended
                $stmt = $db->prepare("UPDATE exit_requests 
                                     SET actual_entry_time = :actual_entry_time, 
                                         actual_entry_date = :actual_entry_date, 
                                         delay_minutes = :delay_minutes,
                                         status = 'suspended' 
                                     WHERE id = :id");
                $stmt->bindParam(':actual_entry_time', $actual_entry_time);
                $stmt->bindParam(':actual_entry_date', $actual_entry_date);
                $stmt->bindParam(':delay_minutes', $delay_minutes);
                $stmt->bindParam(':id', $request_id);
                $stmt->execute();
                
                // Record the delay
                $stmt = $db->prepare("INSERT INTO soldier_delays 
                                     (soldier_id, exit_request_id, delay_date, delay_minutes, recorded_by)
                                     VALUES (:soldier_id, :exit_request_id, :delay_date, :delay_minutes, :recorded_by)");
                $stmt->bindParam(':soldier_id', $entry_info['soldier_id']);
                $stmt->bindParam(':exit_request_id', $request_id);
                $stmt->bindParam(':delay_date', $actual_entry_date);
                $stmt->bindParam(':delay_minutes', $delay_minutes);
                $stmt->bindParam(':recorded_by', $guard_id);
                $stmt->execute();
                
                // Convert delay to a human-readable format
                $hours = floor($delay_minutes / 60);
                $minutes = $delay_minutes % 60;
                $delay_text = '';
                
                if ($hours > 0) {
                    $delay_text .= $hours . ' ساعت ';
                }
                if ($minutes > 0 || $hours == 0) {
                    $delay_text .= $minutes . ' دقیقه';
                }
                
                $max_hours = floor($entry_info['max_delay_minutes'] / 60);
                $max_minutes = $entry_info['max_delay_minutes'] % 60;
                $max_delay_text = '';
                
                if ($max_hours > 0) {
                    $max_delay_text .= $max_hours . ' ساعت ';
                }
                if ($max_minutes > 0 || $max_hours == 0) {
                    $max_delay_text .= $max_minutes . ' دقیقه';
                }
                
                $message = 'سرباز به دلیل تاخیر بیش از حد مجاز (' . $delay_text . ' از ' . $max_delay_text . ' مجاز) به لیست لغو خروجی منتقل شد.';
                
            } else {
                // Normal entry confirmation
                // Update exit request with actual entry time and delay
                $stmt = $db->prepare("UPDATE exit_requests 
                                     SET actual_entry_time = :actual_entry_time, 
                                         actual_entry_date = :actual_entry_date, 
                                         delay_minutes = :delay_minutes,
                                         status = 'completed' 
                                     WHERE id = :id");
                $stmt->bindParam(':actual_entry_time', $actual_entry_time);
                $stmt->bindParam(':actual_entry_date', $actual_entry_date);
                $stmt->bindParam(':delay_minutes', $delay_minutes);
                $stmt->bindParam(':id', $request_id);
                $stmt->execute();
                
                // If there's a delay, record it
                if ($delay_minutes > 0) {
                    // Insert delay record
                    $stmt = $db->prepare("INSERT INTO soldier_delays 
                                         (soldier_id, exit_request_id, delay_date, delay_minutes, recorded_by)
                                         VALUES (:soldier_id, :exit_request_id, :delay_date, :delay_minutes, :recorded_by)");
                    $stmt->bindParam(':soldier_id', $entry_info['soldier_id']);
                    $stmt->bindParam(':exit_request_id', $request_id);
                    $stmt->bindParam(':delay_date', $actual_entry_date);
                    $stmt->bindParam(':delay_minutes', $delay_minutes);
                    $stmt->bindParam(':recorded_by', $guard_id);
                    $stmt->execute();
                    
                    // Convert delay to a human-readable format
                    $hours = floor($delay_minutes / 60);
                    $minutes = $delay_minutes % 60;
                    $delay_text = '';
                    
                    if ($hours > 0) {
                        $delay_text .= $hours . ' ساعت ';
                    }
                    if ($minutes > 0 || $hours == 0) {
                        $delay_text .= $minutes . ' دقیقه';
                    }
                    
                    $message = 'ورود با تاخیر ' . $delay_text . ' ثبت شد.';
                } else {
                    $message = 'ورود با موفقیت ثبت شد.';
                }
            }
        }
    }
    
    // Redirect to refresh the page
    header('Location: ' . BASE_URL . '/guard/index.php?message=' . urlencode($message));
    exit;
}

// Get message from URL
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}

// Get pending exit requests for guard approval
$stmt = $db->prepare("SELECT er.id, er.exit_date, er.exit_time, er.expected_entry_date, er.expected_entry_time, 
                         s.full_name as soldier_name, s.unit as soldier_unit, u.full_name as requester_name
                      FROM exit_requests er
                      INNER JOIN soldiers s ON er.soldier_id = s.id
                      INNER JOIN users u ON er.created_by = u.id
                      WHERE er.status = 'pending'
                      AND er.current_approval_step = (
                          SELECT MAX(approval_order) + 1 FROM soldier_approvers WHERE soldier_id = er.soldier_id
                      )
                      ORDER BY er.exit_date, er.exit_time");
$stmt->execute();
$pending_exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved exits waiting for entry confirmation
$stmt = $db->prepare("SELECT er.id, er.exit_date, er.exit_time, er.expected_entry_date, er.expected_entry_time, 
                         s.full_name as soldier_name, s.unit as soldier_unit
                      FROM exit_requests er
                      INNER JOIN soldiers s ON er.soldier_id = s.id
                      WHERE er.status = 'approved'
                      AND er.actual_entry_date IS NULL
                      AND er.actual_entry_time IS NULL
                      ORDER BY er.expected_entry_date, er.expected_entry_time");
$stmt->execute();
$pending_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل دژبان | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .search-input {
    width: 100%;
    padding: 8px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 4px;
}

    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/guard_sidebar.php'; ?>
    
    <div class="content">
        <h1>پنل دژبان</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>درخواست‌های خروج نیازمند تایید</h2>
            
            <?php if (count($pending_exits) > 0): ?>
            <input type="text" id="search-exit" placeholder="جستجو در جدول خروج‌ها..." class="search-input">

            <div class="table-container">
                <table id="exit-table">
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
                        <?php foreach ($pending_exits as $request): ?>
                        <tr>
                            <td><?php echo $request['soldier_name']; ?></td>
                            <td><?php echo $request['soldier_unit']; ?></td>
                            <td><?php echo formatJalaliDate($request['exit_date']); ?></td>
                            <td><?php echo $request['exit_time']; ?></td>
                            <td><?php echo formatJalaliDate($request['expected_entry_date']); ?></td>
                            <td><?php echo $request['expected_entry_time']; ?></td>
                            <td><?php echo $request['requester_name']; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="showExitModal(<?php echo $request['id']; ?>, '<?php echo $request['soldier_name']; ?>')">بررسی</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>هیچ درخواست خروجی برای تایید وجود ندارد.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>ثبت ورود سربازان</h2>
            
            <?php if (count($pending_entries) > 0): ?>
            <input type="text" id="search-entry" placeholder="جستجو در جدول ورودها..." class="search-input">

            <div class="table-container">
                <table id="entry-table">
                    <thead>
                        <tr>
                            <th>نام سرباز</th>
                            <th>واحد شغلی</th>
                            <th>تاریخ خروج</th>
                            <th>ساعت خروج</th>
                            <th>تاریخ ورود مورد انتظار</th>
                            <th>ساعت ورود مورد انتظار</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_entries as $request): ?>
                        <tr>
                            <td><?php echo $request['soldier_name']; ?></td>
                            <td><?php echo $request['soldier_unit']; ?></td>
                            <td><?php echo formatJalaliDate($request['exit_date']); ?></td>
                            <td><?php echo $request['exit_time']; ?></td>
                            <td><?php echo formatJalaliDate($request['expected_entry_date']); ?></td>
                            <td><?php echo $request['expected_entry_time']; ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('آیا از ثبت ورود این سرباز اطمینان دارید؟');">
                                    <input type="hidden" name="confirm_entry" value="1">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">ثبت ورود</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>هیچ سربازی منتظر ثبت ورود نیست.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Exit Approval Modal -->
    <div id="exitModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeExitModal()">&times;</span>
            <h2>بررسی درخواست خروج</h2>
            <form method="post" action="" id="exitForm">
                <input type="hidden" name="approve_exit" value="1">
                <input type="hidden" name="request_id" id="exit_request_id">
                
                <p>درخواست خروج برای سرباز: <span id="exit_soldier_name"></span></p>
                
                <div class="form-group">
                    <label>توضیحات:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">تایید خروج</button>
                    <button type="submit" name="action" value="deny" class="btn btn-danger">رد خروج</button>
                    <button type="button" class="btn" onclick="closeExitModal()">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
    function showExitModal(requestId, soldierName) {
        document.getElementById('exit_request_id').value = requestId;
        document.getElementById('exit_soldier_name').textContent = soldierName;
        document.getElementById('exitModal').style.display = 'block';
    }
    
    function closeExitModal() {
        document.getElementById('exitModal').style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('exitModal');
        if (event.target == modal) {
            closeExitModal();
        }
    }
    </script>
    <script>
function setupTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

    input.addEventListener("input", function () {
        const filter = this.value.toLowerCase();
        for (let row of rows) {
            const cells = row.getElementsByTagName("td");
            let match = false;
            for (let cell of cells) {
                if (cell.textContent.toLowerCase().includes(filter)) {
                    match = true;
                    break;
                }
            }
            row.style.display = match ? "" : "none";
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    setupTableSearch("search-exit", "exit-table");
    setupTableSearch("search-entry", "entry-table");
});
</script>

</body>
</html>
