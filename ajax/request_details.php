
<?php
// ajax/request_details.php - NEW FILE
require_once '../config.php';
requireLogin();

if (!isset($_GET['id'])) {
    echo '<p class="error">درخواست نامعتبر</p>';
    exit;
}

$request_id = intval($_GET['id']);
$db = getDB();

// Get request details
$stmt = $db->prepare("
    SELECT er.*, s.full_name as soldier_name, s.unit as soldier_unit,
           u.full_name as requester_name, u.unit as requester_unit
    FROM exit_requests er
    INNER JOIN soldiers s ON er.soldier_id = s.id
    INNER JOIN users u ON er.created_by = u.id
    WHERE er.id = :id
");
$stmt->bindParam(':id', $request_id);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo '<p class="error">درخواست مورد نظر یافت نشد</p>';
    exit;
}

// Get approval details
$stmt = $db->prepare("
    SELECT a.id, a.status, a.approved_at, a.notes, a.approval_step,
           COALESCE(u.full_name, g.full_name) as approver_name,
           COALESCE(u.unit, 'دژبان') as approver_unit,
           CASE WHEN g.id IS NOT NULL THEN 1 ELSE 0 END as is_guard
    FROM approvals a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN guards g ON a.guard_id = g.id
    WHERE a.exit_request_id = :request_id
    ORDER BY a.approval_step
");
$stmt->bindParam(':request_id', $request_id);
$stmt->execute();
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expected approvers
$stmt = $db->prepare("
    SELECT sa.approval_order, u.full_name, u.unit
    FROM soldier_approvers sa
    INNER JOIN users u ON sa.user_id = u.id
    WHERE sa.soldier_id = :soldier_id
    ORDER BY sa.approval_order
");
$stmt->bindParam(':soldier_id', $request['soldier_id']);
$stmt->execute();
$expected_approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="request-details">
    <div class="detail-section">
        <h3>اطلاعات درخواست</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">نام سرباز:</span>
                <span class="detail-value"><?php echo $request['soldier_name']; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">واحد شغلی:</span>
                <span class="detail-value"><?php echo $request['soldier_unit']; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">درخواست کننده:</span>
                <span class="detail-value"><?php echo $request['requester_name']; ?> (<?php echo $request['requester_unit']; ?>)</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">تاریخ ثبت:</span>
                <span class="detail-value"><?php echo formatJalaliDate(date('Y-m-d', strtotime($request['created_at']))); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">تاریخ خروج:</span>
                <span class="detail-value"><?php echo formatJalaliDate($request['exit_date']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">ساعت خروج:</span>
                <span class="detail-value"><?php echo $request['exit_time']; ?></span>
            </div>
            <?php if ($request['actual_exit_time']): ?>
<div class="detail-item">
    <span class="detail-label">ساعت خروج واقعی:</span>
    <span class="detail-value"><?php echo $request['actual_exit_time']; ?></span>
</div>
<?php endif; ?>
            <div class="detail-item">
                <span class="detail-label">تاریخ ورود مورد انتظار:</span>
                <span class="detail-value"><?php echo formatJalaliDate($request['expected_entry_date']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">ساعت ورود مورد انتظار:</span>
                <span class="detail-value"><?php echo $request['expected_entry_time']; ?></span>
            </div>
            <?php if ($request['actual_entry_date']): ?>
            <div class="detail-item">
                <span class="detail-label">تاریخ ورود واقعی:</span>
                <span class="detail-value"><?php echo formatJalaliDate($request['actual_entry_date']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($request['actual_entry_time']): ?>
            <div class="detail-item">
                <span class="detail-label">ساعت ورود واقعی:</span>
                <span class="detail-value"><?php echo $request['actual_entry_time']; ?></span>
            </div>
            <?php endif; ?>
            <?php if ($request['direct_admin_request'] == 1): ?>
<div class="detail-item admin-request">
    <span class="detail-label">نوع درخواست:</span>
    <span class="detail-value admin-request-badge">درخواست مستقیم توسط مدیر</span>
</div>
<?php endif; ?>
            <div class="detail-item">
                <span class="detail-label">وضعیت:</span>
                <span class="detail-value">
                    <?php 
                    switch ($request['status']) {
                        case 'pending': echo '<span class="status-badge status-pending">در انتظار تایید</span>'; break;
                        case 'approved': echo '<span class="status-badge status-approved">تایید شده</span>'; break;
                        case 'denied': echo '<span class="status-badge status-denied">رد شده</span>'; break;
                        case 'completed': 
                            if ($request['actual_entry_date'] > $request['expected_entry_date'] || 
                                ($request['actual_entry_date'] == $request['expected_entry_date'] && 
                                 $request['actual_entry_time'] > $request['expected_entry_time'])) {
                                echo '<span class="status-badge status-late">تکمیل شده (با تاخیر)</span>';
                            } else {
                                echo '<span class="status-badge status-completed">تکمیل شده</span>';
                            }
                            break;
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="detail-section">
        <h3>وضعیت تاییدها</h3>
        <div class="approval-timeline">
            <?php
            // First, show requester as first step
            ?>
            <div class="approval-step completed">
                <div class="step-marker"></div>
                <div class="step-content">
                    <div class="step-title">ثبت درخواست</div>
                    <div class="step-info">
                        <span class="approver-name"><?php echo $request['requester_name']; ?></span>
                        <span class="approval-date"><?php echo formatJalaliDate(date('Y-m-d', strtotime($request['created_at']))); ?></span>
                    </div>
                </div>
            </div>
            
            <?php
            // Then show expected approval flow with status
            $last_approval_step = 0;
            foreach ($expected_approvers as $index => $approver):
                $approval_order = $approver['approval_order'];
                $approval = null;
                foreach ($approvals as $a) {
                    if ($a['approval_step'] == $approval_order && $a['is_guard'] == 0) {
                        $approval = $a;
                        $last_approval_step = max($last_approval_step, $approval_order);
                        break;
                    }
                }
                
                $status_class = 'pending';
                if ($approval) {
                    $status_class = $approval['status'];
                } elseif ($request['current_approval_step'] > $approval_order || $request['status'] == 'denied') {
                    $status_class = 'skipped';
                }
            ?>
            <div class="approval-step <?php echo $status_class; ?>">
                <div class="step-marker"></div>
                <div class="step-content">
                    <div class="step-title">تایید کننده <?php echo $index + 1; ?></div>
                    <div class="step-info">
                        <span class="approver-name"><?php echo $approver['full_name']; ?> (<?php echo $approver['unit']; ?>)</span>
                        <?php if ($approval): ?>
                        <span class="approval-date"><?php echo formatJalaliDate(date('Y-m-d', strtotime($approval['approved_at']))); ?></span>
                        <?php if ($approval['notes']): ?>
                        <div class="approval-notes"><?php echo $approval['notes']; ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php
            // Finally show guard approval (if applicable)
            $guard_approval = null;
            foreach ($approvals as $a) {
                if ($a['is_guard'] == 1) {
                    $guard_approval = $a;
                    break;
                }
            }
            
            $guard_status = 'pending';
            if ($guard_approval) {
                $guard_status = $guard_approval['status'];
            } elseif ($request['status'] == 'denied' || ($expected_approvers && $request['current_approval_step'] <= count($expected_approvers))) {
                $guard_status = 'skipped';
            }
            ?>
            <div class="approval-step <?php echo $guard_status; ?>">
                <div class="step-marker"></div>
                <div class="step-content">
                    <div class="step-title">تایید نهایی دژبان</div>
                    <div class="step-info">
                        <?php if ($guard_approval): ?>
                        <span class="approver-name"><?php echo $guard_approval['approver_name']; ?></span>
                        <span class="approval-date"><?php echo formatJalaliDate(date('Y-m-d', strtotime($guard_approval['approved_at']))); ?></span>
                        <?php if ($guard_approval['notes']): ?>
                        <div class="approval-notes"><?php echo $guard_approval['notes']; ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($request['actual_exit_time'] && $request['status'] == 'approved'): ?>
<div class="approval-step completed">
    <div class="step-marker"></div>
    <div class="step-content">
        <div class="step-title">ثبت زمان خروج واقعی</div>
        <div class="step-info">
            <span class="approval-date">ساعت <?php echo $request['actual_exit_time']; ?></span>
            <div class="actual-time">خروج از پادگان</div>
        </div>
    </div>
</div>
<?php endif; ?>
            <?php if ($request['actual_entry_date'] && $request['status'] == 'completed'): ?>
            <div class="approval-step completed">
                <div class="step-marker"></div>
                <div class="step-content">
                    <div class="step-title">ثبت ورود</div>
                    <div class="step-info">
                        <span class="approval-date">
                            <?php echo formatJalaliDate($request['actual_entry_date']); ?> ساعت <?php echo $request['actual_entry_time']; ?>
                        </span>
                        <?php if ($request['actual_entry_date'] > $request['expected_entry_date'] || 
                                  ($request['actual_entry_date'] == $request['expected_entry_date'] && 
                                   $request['actual_entry_time'] > $request['expected_entry_time'])): ?>
                        <div class="late-notice">
                            با تاخیر
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>