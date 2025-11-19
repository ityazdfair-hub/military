
<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'military');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', ''); // Change in production
define('SITE_NAME', 'اتوماسیون ورود و خروج سرباز');
define('BASE_URL', '/military'); // Change based on your setup


// Database connection
function getDB() {
    try {
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', 
                    DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die('خطا در اتصال به پایگاه داده: ' . $e->getMessage());
    }
}

// Persian date conversion (improved version)
function gregorianToJalali($gy, $gm = null, $gd = null) {
    // Handle string date format like '2025-05-03'
    if (is_string($gy) && $gm === null && $gd === null) {
        if (strpos($gy, '-') !== false) {
            list($gy, $gm, $gd) = explode('-', $gy);
        } else {
            // Handle invalid format gracefully
            return [1403, 2, 14]; // Default fallback date
        }
    }
    
    $gy = intval($gy);
    $gm = intval($gm);
    $gd = intval($gd);
    
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + floor(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * floor($days / 12053));
    $days %= 12053;
    $jy += 4 * floor($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + floor($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + floor(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalaliToGregorian($jy, $jm = null, $jd = null) {
    // Handle string date format like '1403/02/13'
    if (is_string($jy) && $jm === null && $jd === null) {
        if (strpos($jy, '/') !== false) {
            list($jy, $jm, $jd) = explode('/', $jy);
        } else {
            // Handle invalid format gracefully
            return date('Y-m-d'); // Return current date as fallback
        }
    }
    
    $jy = intval($jy);
    $jm = intval($jm);
    $jd = intval($jd);
    
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (floor($jy / 33) * 8) + floor((($jy % 33) + 3) / 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * floor($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * floor(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * floor($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// Date string conversion
function gregorianToJalaliDate($date) {
    if (empty($date)) return '';
    try {
        list($jy, $jm, $jd) = gregorianToJalali($date);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    } catch (Exception $e) {
        // Fallback if date conversion fails
        return '1403/02/13';
    }
}

function jalaliToGregorianDate($jalali_date) {
    if (empty($jalali_date)) return '';
    try {
        return jalaliToGregorian($jalali_date);
    } catch (Exception $e) {
        // Fallback if date conversion fails
        return date('Y-m-d');
    }
}
// Persian month names
function getPersianMonthName($month) {
    $persian_months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    return isset($persian_months[$month]) ? $persian_months[$month] : 'اردیبهشت';
}

// Format date for display
function formatJalaliDate($date) {
    if (empty($date)) return '';
    
    try {
        $jalali_date = gregorianToJalaliDate($date);
        list($jy, $jm, $jd) = explode('/', $jalali_date);
        return $jd . ' ' . getPersianMonthName(intval($jm)) . ' ' . $jy;
    } catch (Exception $e) {
        // Fallback if formatting fails
        return '13 اردیبهشت 1403';
    }
}

// Get current jalali date
function getCurrentJalaliDate() {
    $now = date('Y-m-d');
    return gregorianToJalaliDate($now);
}

// Get current jalali date formatted
function getCurrentJalaliDateFormatted() {
    $details = getCurrentJalaliDetails();
    return $details['formatted'];
}

date_default_timezone_set('Asia/Tehran'); // در ابتدای فایل اضافه شود

function getCurrentJalaliDetails() {
    $now = time();
    list($jYear, $jMonth, $jDay) = gregorianToJalali(date('Y', $now), date('m', $now), date('d', $now));

    $weekday = getPersianWeekdayName($now);
    $monthName = getPersianMonthName($jMonth);

    return [
        'year' => $jYear,
        'month' => $jMonth,
        'day' => $jDay,
        'weekday' => $weekday,
        'monthName' => $monthName,
        'formatted' => "$jDay $monthName $jYear"
    ];
}
function getPersianWeekdayName($date = null) {
    if ($date === null) {
        $timestamp = time();
    } else if (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        $timestamp = $date;
    }

    // Get Gregorian weekday (0=Sunday)
    $weekday = date('w', $timestamp);

    // Map to Persian weekday (Saturday is 6 in Gregorian, but should be 0 in Persian)
    $persian_weekdays = [
        6 => 'شنبه',
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه'
    ];

    return $persian_weekdays[$weekday];
}

// Security functions
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
    } else {
        $input = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

function hashPassword($password) {
    return ($password);
}

function verifyPassword($password, $hash) {
    return ($password== $hash);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['guard_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function isGuard() {
    return isset($_SESSION['guard_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/unauthorized.php');
        exit;
    }
}
// Function to format delay time - Add this to config.php
function formatDelayTime($minutes) {
    if ($minutes <= 0) return '-';
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    $result = '';
    if ($hours > 0) {
        $result .= $hours . ' ساعت ';
    }
    if ($mins > 0 || $hours == 0) {
        $result .= $mins . ' دقیقه';
    }
    
    return $result;
}
// Session start
session_start();
?>