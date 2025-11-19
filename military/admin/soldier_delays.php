
<?php
// admin/soldier_delays.php - New file for detailed delay information for a specific soldier
?>
<?php
// admin/soldier_delays.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$message = '';

// Get soldier ID from URL
if (!isset($_GET['soldier_id'])) {
    header('Location: ' . BASE_URL . '/admin/delayed_soldiers.php');
    exit;
}

$soldier_id = intval($_GET['soldier_id']);

// Get soldier details
$stmt = $db->prepare("SELECT * FROM soldiers WHERE id = :id");
$stmt->bindParam(':id', $soldier_id);
$stmt->execute();
$soldier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$soldier) {
    header('Location: ' . BASE_URL . '/admin/delayed_soldiers.php');
    exit;
}

// Filter parameters
$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : date('Y-m-d');

// Get delay details for this soldier
$stmt = $db->prepare("SELECT sd.id, sd.delay_date, sd.delay_minutes, 
                             er.exit_date, er.exit_time, er.actual_exit_time,
                             er.expected_entry_date, er.expected_entry_time,
                             er.actual_entry_date, er.actual_entry_time,
                             g.full_name as guard_name
                      FROM soldier_delays sd
                      INNER JOIN exit_requests er ON sd.exit_request_id = er.id
                      INNER JOIN guards g ON sd.recorded_by = g.id
                      WHERE sd.soldier_id = :soldier_id
                      AND sd.delay_date BETWEEN :from_date AND :to_date
                      ORDER BY sd.delay_date DESC, sd.id DESC");
$stmt->bindParam(':soldier_id', $soldier_id);
$stmt->bindParam(':from_date', $from_date);
$stmt->bindParam(':to_date', $to_date);
$stmt->execute();
$delays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_delays = count($delays);
$total_delay_minutes = 0;
$max_delay = 0;

foreach ($delays as $delay) {
    $total_delay_minutes += $delay['delay_minutes'];
    if ($delay['delay_minutes'] > $max_delay) {
        $max_delay = $delay['delay_minutes'];
    }
}

$avg_delay = ($total_delays > 0) ? round($total_delay_minutes / $total_delays) : 0;

// Function to format delay in hours and minutes
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات تاخیر سرباز | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
/* Delay Reports Styling */
.delay-summary, .delay-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.summary-card, .stat-card {
    background-color: #fff;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    text-align: center;
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    border-top: 3px solid #3b82f6;
}

.summary-value, .stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #1e40af;
    margin-bottom: 0.5rem;
}

.summary-label, .stat-label {
    color: #6b7280;
    font-size: 0.9rem;
}

.high-delay {
    background-color: rgba(239, 68, 68, 0.1) !important;
}

.delay-value {
    font-weight: bold;
    color: #ef4444;
}

.soldier-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.soldier-detail {
    display: flex;
    flex-direction: column;
    background-color: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
}

.detail-label {
    font-weight: bold;
    color: #4b5563;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.detail-value {
    font-size: 1.1rem;
}

/* Print styles for delay reports */
@media print {
    .delay-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
    
    .soldier-info {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
    
    .high-delay {
        background-color: #ffebee !important;
    }
}
</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>جزئیات تاخیر سرباز</h1>
        
        <div class="card">
            <h2>اطلاعات سرباز</h2>
            
            <div class="soldier-info">
                <div class="soldier-detail">
                    <span class="detail-label">نام و نام خانوادگی:</span>
                    <span class="detail-value"><?php echo $soldier['full_name']; ?></span>
                </div>
                
                <div class="soldier-detail">
                    <span class="detail-label">نام پدر:</span>
                    <span class="detail-value"><?php echo $soldier['father_name']; ?></span>
                </div>
                
                <div class="soldier-detail">
                    <span class="detail-label">کد ملی:</span>
                    <span class="detail-value"><?php echo $soldier['national_id']; ?></span>
                </div>
                
                <div class="soldier-detail">
                    <span class="detail-label">واحد شغلی:</span>
                    <span class="detail-value"><?php echo $soldier['unit']; ?></span>
                </div>
            </div>
        </div>
        
    
      
        
        <div class="card">
            <h2>آمار تاخیر</h2>
            
            <div class="delay-stats">
                <div class="stat-card">
                    <span class="stat-value"><?php echo $total_delays; ?></span>
                    <span class="stat-label">تعداد کل تاخیرها</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo formatDelayTime($total_delay_minutes); ?></span>
                    <span class="stat-label">مجموع زمان تاخیر</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo formatDelayTime($max_delay); ?></span>
                    <span class="stat-label">بیشترین تاخیر</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo formatDelayTime($avg_delay); ?></span>
                    <span class="stat-label">میانگین تاخیر</span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>جزئیات تاخیرها</h2>
            
            <?php if (count($delays) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>زمان خروج</th>
                            <th>زمان خروج مورد انتظار</th>
                            <th>زمان خروج واقعی</th>
                            <th>زمان ورود مورد انتظار</th>
                            <th>زمان ورود واقعی</th>
                            <th>میزان تاخیر</th>
                            <th>ثبت کننده</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($delays as $delay): ?>
                        <tr class="<?php echo ($delay['delay_minutes'] > 120) ? 'high-delay' : ''; ?>">
                            <td><?php echo formatJalaliDate($delay['delay_date']); ?></td>
                            <td><?php echo $delay['exit_time']; ?></td>
                            <td><?php echo formatJalaliDate($delay['exit_date']); ?> <?php echo $delay['exit_time']; ?></td>
                            <td><?php echo $delay['actual_exit_time'] ? $delay['actual_exit_time'] : '-'; ?></td>
                            <td><?php echo formatJalaliDate($delay['expected_entry_date']); ?> <?php echo $delay['expected_entry_time']; ?></td>
                            <td><?php echo formatJalaliDate($delay['actual_entry_date']); ?> <?php echo $delay['actual_entry_time']; ?></td>
                            <td class="delay-value"><?php echo formatDelayTime($delay['delay_minutes']); ?></td>
                            <td><?php echo $delay['guard_name']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="actions mt-3">
             
                <a href="delayed_soldiers.php" class="btn">بازگشت به لیست</a>
            </div>
            <?php else: ?>
            <p>هیچ تاخیری برای این سرباز ثبت نشده است.</p>
            <div class="actions mt-3">
                <a href="delayed_soldiers.php" class="btn">بازگشت به لیست</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include Date Picker Modal and Scripts -->
  
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
    function printReport() {
        window.print();
    }
    </script>
</body>
</html>