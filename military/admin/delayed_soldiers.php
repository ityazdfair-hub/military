
<?php
// admin/delayed_soldiers.php - New file for monitoring soldiers with delays
?>
<?php
// admin/delayed_soldiers.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$message = '';

// Filter parameters
$from_date = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : date('Y-m-d');
$min_delay = isset($_GET['min_delay']) ? intval($_GET['min_delay']) : 30; // Default 30 minutes
$unit_filter = isset($_GET['unit']) ? sanitize($_GET['unit']) : '';

// Get all units for filter dropdown
$stmt = $db->query("SELECT DISTINCT unit FROM soldiers ORDER BY unit");
$units = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query based on filters
$params = [];
$query = "SELECT s.id as soldier_id, s.full_name, s.father_name, s.national_id, s.unit,
                 COUNT(sd.id) as delay_count,
                 SUM(sd.delay_minutes) as total_delay_minutes,
                 MAX(sd.delay_minutes) as max_delay_minutes,
                 AVG(sd.delay_minutes) as avg_delay_minutes
          FROM soldiers s
          INNER JOIN soldier_delays sd ON s.id = sd.soldier_id
          WHERE sd.delay_date BETWEEN :from_date AND :to_date
          AND sd.delay_minutes >= :min_delay";

$params[':from_date'] = $from_date;
$params[':to_date'] = $to_date;
$params[':min_delay'] = $min_delay;

if (!empty($unit_filter)) {
    $query .= " AND s.unit = :unit";
    $params[':unit'] = $unit_filter;
}

$query .= " GROUP BY s.id, s.full_name, s.father_name, s.national_id, s.unit
            ORDER BY total_delay_minutes DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$delayed_soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format delay in hours and minutes

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سربازان با تاخیر | <?php echo SITE_NAME; ?></title>
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
 #searchInput {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            direction: rtl;
            font-size: 1rem;
        }
</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>گزارش سربازان با تاخیر</h1>
        
        <div class="card">
            <h2>فیلترها</h2>
            
            <form method="get" action="" class="filter-form">
                
                
                <div class="form-row">
                    <div class="form-group">
                        <label>حداقل تاخیر (دقیقه):</label>
                        <input type="number" name="min_delay" value="<?php echo $min_delay; ?>" min="1" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label>واحد شغلی:</label>
                        <select name="unit">
                            <option value="">همه واحدها</option>
                            <?php foreach ($units as $unit): ?>
                            <option value="<?php echo $unit; ?>" <?php echo ($unit_filter == $unit) ? 'selected' : ''; ?>>
                                <?php echo $unit; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                    <a href="delayed_soldiers.php" class="btn">حذف فیلتر</a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>سربازان با تاخیر</h2>
            
            <?php if (count($delayed_soldiers) > 0): ?>
            <div class="delay-summary">
                <div class="summary-card">
                    <span class="summary-value"><?php echo count($delayed_soldiers); ?></span>
                    <span class="summary-label">تعداد سربازان با تاخیر</span>
                </div>
            </div>

            <div class="table-container">            <input type="text" id="searchInput" placeholder="جستجو..." style="margin-bottom: 10px; padding: 5px; width: 100%;">

                <table id="dataTable">
                    <thead>
                        <tr>
                            <th>نام و نام خانوادگی</th>
                            <th>نام پدر</th>
                            <th>کد ملی</th>
                            <th>واحد شغلی</th>
                            <th>تعداد تاخیر</th>
                            <th>مجموع تاخیر</th>
                            <th>بیشترین تاخیر</th>
                            <th>میانگین تاخیر</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($delayed_soldiers as $soldier): ?>
                        <tr class="<?php echo ($soldier['total_delay_minutes'] > 180) ? 'high-delay' : ''; ?>">
                            <td><?php echo $soldier['full_name']; ?></td>
                            <td><?php echo $soldier['father_name']; ?></td>
                            <td><?php echo $soldier['national_id']; ?></td>
                            <td><?php echo $soldier['unit']; ?></td>
                            <td><?php echo $soldier['delay_count']; ?> مورد</td>
                            <td><?php echo formatDelayTime($soldier['total_delay_minutes']); ?></td>
                            <td><?php echo formatDelayTime($soldier['max_delay_minutes']); ?></td>
                            <td><?php echo formatDelayTime(round($soldier['avg_delay_minutes'])); ?></td>
                            <td>
                                <a href="soldier_delays.php?soldier_id=<?php echo $soldier['soldier_id']; ?>" class="btn btn-sm">جزئیات</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
           
            <?php else: ?>
            <p>هیچ سربازی با تاخیر یافت نشد.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include Date Picker Modal and Scripts -->
    
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
    function printReport() {
        window.print();
    }
    document.getElementById("searchInput").addEventListener("keyup", function () {
    var input = this.value.toLowerCase();
    var rows = document.querySelectorAll("#dataTable tbody tr");

    rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
});



    </script>
</body>
</html>