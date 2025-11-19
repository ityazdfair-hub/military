<?php
// ajax/soldiers_in_base.php
require_once '../config.php';
requireLogin();

if (!isAdmin()) {
    echo '<div class="error">دسترسی غیرمجاز</div>';
    exit;
}

$db = getDB();

// Get soldiers currently in the base
$query = "
    SELECT s.id, s.full_name, s.father_name, s.unit
    FROM soldiers s
    LEFT JOIN exit_requests er ON s.id = er.soldier_id AND er.status = 'approved' 
        AND er.actual_entry_date IS NULL
    WHERE er.id IS NULL OR er.status != 'approved'
    ORDER BY s.full_name
";

$stmt = $db->query($query);
$soldiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($soldiers) == 0) {
    echo '<div class="info">هیچ سربازی در پادگان حضور ندارد.</div>';
} else {
    // Add search input
    echo '<div class="search-container">';
    echo '<input type="text" id="soldierSearchInput" class="search-input" placeholder="جستجوی نام سرباز..." onkeyup="filterSoldiers()">';
    echo '</div>';
    
    // Display all soldiers in a single list
    echo '<div class="soldiers-in-base">';
    echo '<ul id="soldiersList" class="soldiers-list">';
    
    foreach ($soldiers as $soldier) {
        echo '<li>';
        echo '<span class="soldier-name">' . $soldier['full_name'] . '</span>';
        echo '<span class="soldier-father">فرزند ' . $soldier['father_name'] . '</span>';
        echo '<span class="soldier-unit">' . $soldier['unit'] . '</span>';
        echo '</li>';
    }
    
    echo '</ul>';
    echo '</div>';
    
  
}
?>