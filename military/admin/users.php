
<?php
// admin/users.php
require_once '../config.php';
requireAdmin();

$db = getDB();
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $unit = sanitize($_POST['unit']);
        $username = sanitize($_POST['username']);
        $military_code = sanitize($_POST['military_code']);
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if ($action == 'add') {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $message = 'این نام کاربری قبلاً ثبت شده است';
            } else {
                $password = $_POST['password'];
                //$hashed_password = hashPassword($password);
                $hashed_password = ($password);
                // Add new user
                $stmt = $db->prepare("INSERT INTO users (full_name, phone, unit, username, password, military_code, is_admin) 
                                      VALUES (:full_name, :phone, :unit, :username, :password, :military_code, :is_admin)");
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':unit', $unit);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':military_code', $military_code);
                $stmt->bindParam(':is_admin', $is_admin);
                
                if ($stmt->execute()) {
                    $message = 'کاربر با موفقیت اضافه شد';
                    header('Location: users.php?message=' . urlencode($message));
                    exit;
                } else {
                    $message = 'خطا در ثبت اطلاعات';
                }
            }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                //$hashed_password = hashPassword($password);
                $hashed_password = ($password);
                // Update user with new password
                $stmt = $db->prepare("UPDATE users SET full_name = :full_name, phone = :phone, unit = :unit, 
                                     username = :username, password = :password, military_code = :military_code, 
                                     is_admin = :is_admin WHERE id = :id");
                $stmt->bindParam(':password', $hashed_password);
            } else {
                // Update user without changing password
                $stmt = $db->prepare("UPDATE users SET full_name = :full_name, phone = :phone, unit = :unit, 
                                     username = :username, military_code = :military_code, 
                                     is_admin = :is_admin WHERE id = :id");
            }
            
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':unit', $unit);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':military_code', $military_code);
            $stmt->bindParam(':is_admin', $is_admin);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $message = 'اطلاعات کاربر با موفقیت بروزرسانی شد';
                header('Location: users.php?message=' . urlencode($message));
                exit;
            } else {
                $message = 'خطا در بروزرسانی اطلاعات';
            }
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        
        // Don't allow deletion of own account
        if ($id == $_SESSION['user_id']) {
            $message = 'شما نمی‌توانید حساب کاربری خود را حذف کنید';
        } else {
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $message = 'کاربر با موفقیت حذف شد';
                header('Location: users.php?message=' . urlencode($message));
                exit;
            } else {
                $message = 'خطا در حذف کاربر';
            }
        }
    }
}

// Get message from URL
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY full_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user data for edit
$user = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>مدیریت کاربران</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($action == 'add' || ($action == 'edit' && $user)): ?>
        <div class="card">
            <h2><?php echo ($action == 'add') ? 'افزودن کاربر جدید' : 'ویرایش اطلاعات کاربر'; ?></h2>
            
            <form method="post" action="">
                <?php if ($action == 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>نام و نام خانوادگی:</label>
                    <input type="text" name="full_name" value="<?php echo ($user) ? $user['full_name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>شماره تماس:</label>
                    <input type="text" name="phone" value="<?php echo ($user) ? $user['phone'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>واحد شغلی:</label>
                    <input type="text" name="unit" value="<?php echo ($user) ? $user['unit'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" value="<?php echo ($user) ? $user['username'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>رمز عبور:<?php echo ($action == 'edit') ? ' (در صورت عدم تغییر خالی بگذارید)' : ''; ?></label>
                    <input type="password" name="password" <?php echo ($action == 'add') ? 'required' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label>کد پاسداری:</label>
                    <input type="text" name="military_code" value="<?php echo ($user) ? $user['military_code'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_admin" <?php echo ($user && $user['is_admin'] == 1) ? 'checked' : ''; ?>>
                        دسترسی مدیریت سیستم
                    </label>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="users.php" class="btn">انصراف</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="actions">
            <a href="?action=add" class="btn btn-primary">افزودن کاربر جدید</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>نام و نام خانوادگی</th>
                        <th>شماره تماس</th>
                        <th>واحد شغلی</th>
                        <th>نام کاربری</th>
                        <th>کد پاسداری</th>
                        <th>سطح دسترسی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['full_name']; ?></td>
                            <td><?php echo $user['phone']; ?></td>
                            <td><?php echo $user['unit']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['military_code']; ?></td>
                            <td><?php echo ($user['is_admin'] == 1) ? 'مدیر' : 'کاربر عادی'; ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm">ویرایش</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="post" action="?action=delete" style="display: inline;" onsubmit="return confirm('آیا از حذف این کاربر اطمینان دارید؟');">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">هیچ کاربری ثبت نشده است.</td>
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
