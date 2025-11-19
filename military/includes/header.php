<header>
    <div class="logo">
        <h1 style="color:white;"><?php echo SITE_NAME; ?></h1>
    </div>
    <div class="date-time">
        <?php $jalaliDetails = getCurrentJalaliDetails(); ?>
        <div class="date"><?php echo $jalaliDetails['weekday']; ?>، <?php echo $jalaliDetails['formatted']; ?></div>
        <div class="time" id="current-time"><?php echo date('H:i:s'); ?></div>
    </div>
    <div class="user-info">
        <?php if (isLoggedIn()): ?>
            <?php if (isGuard()): ?>
                <span>دژبان: <?php echo $_SESSION['guard_name']; ?></span>
            <?php else: ?>
                <span><?php echo $_SESSION['user_name']; ?></span>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm">خروج</a>
        <?php endif; ?>
    </div>
</header>

<script>
// Update the time every second
function updateTime() {
    var now = new Date();
    var hours = now.getHours().toString().padStart(2, '0');
    var minutes = now.getMinutes().toString().padStart(2, '0');
    var seconds = now.getSeconds().toString().padStart(2, '0');
    document.getElementById('current-time').textContent = hours + ':' + minutes + ':' + seconds;
}

// Update immediately and then every second
updateTime();
setInterval(updateTime, 1000);
</script>