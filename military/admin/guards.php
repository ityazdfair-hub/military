
<?php
// admin/guards.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $full_name = sanitize($_POST['full_name']);
        $national_id = sanitize($_POST['national_id']);
        $username = sanitize($_POST['username']);
        
        if ($action == 'add') {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM guards WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $message = 'این نام کاربری قبلاً ثبت شده است';
            } else {
                $password = $_POST['password'];
                $hashed_password =($password);
                
                // Add new guard
                $stmt = $db->prepare("INSERT INTO guards (full_name, national_id, username, password) 
                                      VALUES (:full_name, :national_id, :username, :password)");
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':national_id', $national_id);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                
                if ($stmt->execute()) {
                    $message = 'دژبان با موفقیت اضافه شد';
                    header('Location: guards.php?message=' . urlencode($message));
                    exit;
                } else {
                    $message = 'خطا در ثبت اطلاعات';
                }
            }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                $hashed_password =($password);
                
                // Update guard with new password
                $stmt = $db->prepare("UPDATE guards SET full_name = :full_name, national_id = :national_id, 
                                     username = :username, password = :password WHERE id = :id");
                $stmt->bindParam(':password', $hashed_password);
            } else {
                // Update guard without changing password
                $stmt = $db->prepare("UPDATE guards SET full_name = :full_name, national_id = :national_id, 
                                     username = :username WHERE id = :id");
            }
            
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':national_id', $national_id);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $message = 'اطلاعات دژبان با موفقیت بروزرسانی شد';
                header('Location: guards.php?message=' . urlencode($message));
                exit;
            } else {
                $message = 'خطا در بروزرسانی اطلاعات';
            }
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        
        // Delete guard
        $stmt = $db->prepare("DELETE FROM guards WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $message = 'دژبان با موفقیت حذف شد';
            header('Location: guards.php?message=' . urlencode($message));
            exit;
        } else {
            $message = 'خطا در حذف دژبان';
        }
    }
}

// Get message from URL
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}

// Get all guards
$stmt = $db->query("SELECT * FROM guards ORDER BY full_name");
$guards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get guard data for edit
$guard = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM guards WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $guard = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دژبان‌ها | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>مدیریت دژبان‌ها</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($action == 'add' || ($action == 'edit' && $guard)): ?>
        <div class="card">
            <h2><?php echo ($action == 'add') ? 'افزودن دژبان جدید' : 'ویرایش اطلاعات دژبان'; ?></h2>
            
            <form method="post" action="">
                <?php if ($action == 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $guard['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>نام و نام خانوادگی:</label>
                    <input type="text" name="full_name" value="<?php echo ($guard) ? $guard['full_name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>کد ملی:</label>
                    <input type="text" name="national_id" value="<?php echo ($guard) ? $guard['national_id'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" value="<?php echo ($guard) ? $guard['username'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>رمز عبور:<?php echo ($action == 'edit') ? ' (در صورت عدم تغییر خالی بگذارید)' : ''; ?></label>
                    <input type="password" name="password" <?php echo ($action == 'add') ? 'required' : ''; ?>>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="guards.php" class="btn">انصراف</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="actions">
            <a href="?action=add" class="btn btn-primary">افزودن دژبان جدید</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>نام و نام خانوادگی</th>
                        <th>کد ملی</th>
                        <th>نام کاربری</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($guards) > 0): ?>
                        <?php foreach ($guards as $guard): ?>
                        <tr>
                            <td><?php echo $guard['full_name']; ?></td>
                            <td><?php echo $guard['national_id']; ?></td>
                            <td><?php echo $guard['username']; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $guard['id']; ?>" class="btn btn-sm">ویرایش</a>
                                <form method="post" action="?action=delete" style="display: inline;" onsubmit="return confirm('آیا از حذف این دژبان اطمینان دارید؟');">
                                    <input type="hidden" name="id" value="<?php echo $guard['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">هیچ دژبانی ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
