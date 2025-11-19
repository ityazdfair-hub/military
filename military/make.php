<?php
// آرایه‌ای از مسیرهای پوشه‌ها
$folders = [
    'assets/css',
    'assets/fonts',
    'admin',
    'guard',
    'includes'
];

// آرایه‌ای از مسیرهای فایل‌ها با محتوای اولیه (اختیاری)
$files = [
    'assets/css/style.css' => '/* استایل اولیه */',
    'assets/fonts/Vazir.eot' => '',
    'assets/fonts/Vazir.ttf' => '',
    'assets/fonts/Vazir.woff' => '',
    'assets/fonts/Vazir.woff2' => '',
    'admin/index.php' => '<?php echo "صفحه مدیریت"; ?>',
    'admin/soldiers.php' => '<?php echo "سربازها"; ?>',
    'admin/users.php' => '<?php echo "کاربران"; ?>',
    'admin/guards.php' => '<?php echo "نگهبان‌ها"; ?>',
    'admin/reports.php' => '<?php echo "گزارش‌ها"; ?>',
    'guard/index.php' => '<?php echo "صفحه نگهبان"; ?>',
    'guard/history.php' => '<?php echo "تاریخچه نگهبانی"; ?>',
    'includes/header.php' => '<!-- Header -->',
    'includes/footer.php' => '<!-- Footer -->',
    'includes/admin_sidebar.php' => '<!-- Admin Sidebar -->',
    'includes/user_sidebar.php' => '<!-- User Sidebar -->',
    'includes/guard_sidebar.php' => '<!-- Guard Sidebar -->',
    'config.php' => '<?php // تنظیمات ?>',
    'index.php' => '<?php echo "صفحه اصلی"; ?>',
    'login.php' => '<?php echo "ورود"; ?>',
    'logout.php' => '<?php echo "خروج"; ?>',
    'my_requests.php' => '<?php echo "درخواست‌های من"; ?>',
    'unauthorized.php' => '<?php echo "دسترسی غیرمجاز"; ?>',
];

// ساخت پوشه‌ها
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
        echo "📁 پوشه ساخته شد: $folder\n";
    }
}

// ساخت فایل‌ها
foreach ($files as $path => $content) {
    if (!file_exists($path)) {
        file_put_contents($path, $content);
        echo "📄 فایل ساخته شد: $path\n";
    }
}
