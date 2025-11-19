<?php
// admin/suspended_soldiers.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$message = '';

// Handle unsuspend action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unsuspend'])) {
    $suspension_id = intval($_POST['suspension_id']);
    $user_id = $_SESSION['user_id'];
    
    // Check if user can unsuspend (admin or original suspender)
    $stmt = $db->prepare("SELECT suspended_by FROM exit_suspensions WHERE id = :id");
    $stmt->bindParam(':id', $suspension_id);
    $stmt->execute();
    $suspended_by = $stmt->fetchColumn();
    
    if (isAdmin() || $suspended_by == $user_id) {
        $stmt = $db->prepare("UPDATE exit_suspensions SET is_active = 0 WHERE id = :id");
        $stmt->bindParam(':id', $suspension_id);
        $stmt->execute();
        
        $message = 'سرباز از لیست لغو خروجی حذف شد.';
    } else {
        $message = 'شما مجاز به حذف این سرباز از لیست نیستید.';
    }
}

// Handle manual suspension
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['suspend_soldier'])) {
    $soldier_id = intval($_POST['soldier_id']);
    $notes = sanitize($_POST['notes']);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("INSERT INTO exit_suspensions (soldier_id, reason, suspended_by, notes)
                         VALUES (:soldier_id, 'manual', :suspended_by, :notes)");
    $stmt->bindParam(':soldier_id', $soldier_id);
    $stmt->bindParam(':suspended_by', $user_id);
    $stmt->bindParam(':notes', $notes);
    $stmt->execute();
    
    $message = 'سرباز به لیست لغو خروجی اضافه شد.';
}

// Get suspended soldiers
$stmt = $db->prepare("
    SELECT es.id as suspension_id, es.reason, es.suspended_at, es.notes,
           s.full_name, s.father_name, s.national_id, s.unit,
           u.full_name as suspended_by_name
    FROM exit_suspensions es
    INNER JOIN soldiers s ON es.soldier_id = s.id
    INNER JOIN users u ON es.suspended_by = u.id
    WHERE es.is_active = 1
    ORDER BY es.suspended_at DESC
");
$stmt->execute();
$suspended_soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all soldiers for manual suspension
$stmt = $db->prepare("
    SELECT s.id, s.full_name, s.unit
    FROM soldiers s
    LEFT JOIN exit_suspensions es ON s.id = es.soldier_id AND es.is_active = 1
    WHERE es.id IS NULL
    ORDER BY s.full_name
");
$stmt->execute();
$available_soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لغو خروجی سربازان | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>لغو خروجی سربازان</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>افزودن دستی به لیست لغو خروجی</h2>
            
            <?php if (count($available_soldiers) > 0): ?>
            <form method="post" action="">
                <input type="hidden" name="suspend_soldier" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>انتخاب سرباز:</label>
                        <select name="soldier_id" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($available_soldiers as $soldier): ?>
                            <option value="<?php echo $soldier['id']; ?>">
                                <?php echo $soldier['full_name']; ?> (<?php echo $soldier['unit']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>دلیل لغو خروجی:</label>
                        <textarea name="notes" rows="3" placeholder="دلیل لغو خروجی را وارد کنید..."></textarea>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">افزودن به لیست لغو خروجی</button>
                </div>
            </form>
            <?php else: ?>
            <p>همه سربازان در لیست لغو خروجی قرار دارند.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>لیست سربازان لغو خروجی</h2>
            
            <?php if (count($suspended_soldiers) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام و نام خانوادگی</th>
                            <th>نام پدر</th>
                            <th>کد ملی</th>
                            <th>واحد شغلی</th>
                            <th>دلیل لغو</th>
                            <th>تاریخ لغو</th>
                            <th>لغو شده توسط</th>
                            <th>توضیحات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suspended_soldiers as $soldier): ?>
                        <tr>
                            <td><?php echo $soldier['full_name']; ?></td>
                            <td><?php echo $soldier['father_name']; ?></td>
                            <td><?php echo $soldier['national_id']; ?></td>
                            <td><?php echo $soldier['unit']; ?></td>
                            <td>
                                <?php echo ($soldier['reason'] == 'manual') ? 'دستی' : 'تاخیر بیش از حد'; ?>
                            </td>
                            <td><?php echo formatJalaliDate(date('Y-m-d', strtotime($soldier['suspended_at']))); ?></td>
                            <td><?php echo $soldier['suspended_by_name']; ?></td>
                            <td><?php echo $soldier['notes']; ?></td>
                            <td>
                                <form method="post" action="" style="display: inline;" 
                                      onsubmit="return confirm('آیا از حذف این سرباز از لیست لغو خروجی اطمینان دارید؟');">
                                    <input type="hidden" name="unsuspend" value="1">
                                    <input type="hidden" name="suspension_id" value="<?php echo $soldier['suspension_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">حذف از لیست</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>هیچ سربازی در لیست لغو خروجی قرار ندارد.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>