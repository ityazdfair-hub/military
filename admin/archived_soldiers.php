<?php
// admin/archived_soldiers.php - New file
require_once '../config.php';
requireAdmin();

$db = getDB();
$message = '';

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore'])) {
    $id = intval($_POST['id']);
    
    // Restore soldier
    $stmt = $db->prepare("UPDATE soldiers SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = :id");
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $message = 'سرباز با موفقیت از بایگانی خارج شد';
    } else {
        $message = 'خطا در خروج از بایگانی';
    }
}

// Get archived soldiers
$stmt = $db->prepare("SELECT s.*, u.full_name as archived_by_name 
                     FROM soldiers s 
                     LEFT JOIN users u ON s.archived_by = u.id 
                     WHERE s.is_archived = 1 
                     ORDER BY s.archived_at DESC");
$stmt->execute();
$archived_soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بایگانی سربازان | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>بایگانی سربازان</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <?php if (count($archived_soldiers) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام و نام خانوادگی</th>
                            <th>نام پدر</th>
                            <th>کد ملی</th>
                            <th>واحد شغلی</th>
                            <th>تاریخ بایگانی</th>
                            <th>بایگانی شده توسط</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_soldiers as $soldier): ?>
                        <tr>
                            <td><?php echo $soldier['full_name']; ?></td>
                            <td><?php echo $soldier['father_name']; ?></td>
                            <td><?php echo $soldier['national_id']; ?></td>
                            <td><?php echo $soldier['unit']; ?></td>
                            <td><?php echo $soldier['archived_at'] ? formatJalaliDate(date('Y-m-d', strtotime($soldier['archived_at']))) : '-'; ?></td>
                            <td><?php echo $soldier['archived_by_name']; ?></td>
                            <td>
                                <form method="post" action="" style="display: inline;" onsubmit="return confirm('آیا از خروج این سرباز از بایگانی اطمینان دارید؟');">
                                    <input type="hidden" name="restore" value="1">
                                    <input type="hidden" name="id" value="<?php echo $soldier['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">خروج از بایگانی</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>هیچ سربازی در بایگانی وجود ندارد.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>