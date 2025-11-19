
<?php
// login.php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $login_type = sanitize($_POST['login_type']);
    
    $db = getDB();
    
    if ($login_type == 'user') {
        $stmt = $db->prepare("SELECT id, full_name, password, is_admin FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($password == $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            if ($_SESSION['is_admin'] == "1"){
                header('Location: ' . BASE_URL .'/admin'. '/index.php');
                exit;
            }
            else{
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            }
            
            
        } else {
            $error = 'نام کاربری یا رمز عبور اشتباه است';
        }
    } elseif ($login_type == 'guard') {
        $stmt = $db->prepare("SELECT id, full_name, password FROM guards WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $guard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guard && ($password == $guard['password'])) {
            $_SESSION['guard_id'] = $guard['id'];
            $_SESSION['guard_name'] = $guard['full_name'];
            header('Location: ' . BASE_URL . '/guard/index.php');
            exit;
        } else {
            $error = 'نام کاربری یا رمز عبور اشتباه است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>ورود به سیستم</h1>
            <h2><?php echo SITE_NAME; ?></h2>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label>نوع ورود:</label>
                    <select name="login_type" required>
                        <option value="user">کارمند / مدیر</option>
                        <option value="guard">دژبان</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>رمز عبور:</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">ورود</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
