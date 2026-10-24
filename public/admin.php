<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$view = get_string('view', 40) ?: 'dashboard';
if (!in_array($view, ['dashboard', 'login', 'case', 'settings', 'officers'], true)) {
    http_response_code(404);
    $view = 'dashboard';
}
$admin = current_user();
if ($view !== 'login' && !is_admin()) {
    flash('error', 'Sign in with an authorized officer account to open the dashboard.');
    redirect_to(admin_url('login'));
}
if ($view === 'login' && is_admin()) {
    redirect_to(admin_url('dashboard'));
}
$pageTitle = match ($view) {
    'login' => 'Officer sign in',
    'case' => 'Report review',
    'settings' => 'Platform settings',
    'officers' => 'Department officers',
    default => 'Officer dashboard',
};
$activePage = '';
require dirname(__DIR__) . '/app/views/partials/header.php';
?>
<div class="admin-layout">
  <aside class="admin-rail">
    <div class="admin-identity"><span class="admin-avatar"><?= h(strtoupper(substr((string)($admin['full_name'] ?? 'CR'), 0, 1))) ?></span><div><strong><?= h($admin['full_name'] ?? 'Officer access') ?></strong><small><?= h($admin['role'] === 'system_admin' ? 'System administrator' : ($admin['department_name'] ?? 'Department officer')) ?></small></div></div>
    <?php if (is_admin()): ?>
      <nav class="admin-nav" aria-label="Officer navigation">
        <a class="<?= $view === 'dashboard' ? 'is-active' : '' ?>" href="<?= h(admin_url('dashboard')) ?>"><span>A</span> Report queue</a>
        <?php if (is_system_admin()): ?><a class="<?= $view === 'settings' ? 'is-active' : '' ?>" href="<?= h(admin_url('settings')) ?>"><span>S</span> System settings</a><a class="<?= $view === 'officers' ? 'is-active' : '' ?>" href="<?= h(admin_url('officers')) ?>"><span>O</span> Officer accounts</a><?php endif; ?>
      </nav>
      <div class="rail-note"><span class="rail-dot"></span><div><strong>Secure officer session</strong><small>Department access is enforced on every report action.</small></div></div>
    <?php endif; ?>
    <a class="rail-public-link" href="<?= h(page_url('home')) ?>">← Back to public portal</a>
  </aside>
  <section class="admin-content">
    <?php if ($view === 'login'): ?>
      <div class="officer-login-wrap"><div class="officer-login-intro"><p class="eyebrow"><span class="eyebrow-dot"></span> Municipal portal</p><h1>Good work starts with a clear view.</h1><p>Sign in with your officer account to verify incoming reports and update your department's work.</p><div class="officer-trust"><span>Access controlled</span><span>Department scoped</span></div></div><div class="auth-card"><p class="eyebrow">Officer access</p><h2>Sign in to CityRemedy</h2><p class="muted">Use your email, password, and department access code.</p><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_login"><label>Officer email<input name="email" type="email" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><label>Department access code<input name="access_code" type="password" required autocomplete="off"></label><button class="button button-primary button-wide" type="submit">Open officer dashboard <span aria-hidden="true">→</span></button></form><a class="officer-link" href="<?= h(url('setup.php')) ?>">First-time setup</a></div></div>
    <?php elseif ($view === 'dashboard'): ?>
      <?php
      $filter = get_string('filter', 20) ?: 'open';
      if (!in_array($filter, ['open', 'resolved', 'rejected', 'all'], true)) $filter = 'open';
      $rows = department_complaint_rows($admin, $filter);
      $metrics = admin_metrics($admin);
      $departmentLabel = $admin['role'] === 'system_admin' ? 'All city departments' : (string)$admin['department_name'];
      $noticeStatement = db()->prepare(
          'SELECT notification_id, title, message, complaint_id, created_at
           FROM notifications WHERE user_id = ? AND is_read = 0
           ORDER BY created_at DESC LIMIT 4'
      );
      $noticeStatement->execute([(int)$admin['user_id']]);
      $adminNotices = $noticeStatement->fetchAll();
      ?>
      <div class="admin-page-heading"><div><p class="eyebrow"><span class="eyebrow-dot"></span> <?= h($departmentLabel) ?></p><h1>Report queue</h1><p>Review incoming reports, record validity, and keep citizens informed.</p></div><span class="admin-date"><?= h(date('l, F j')) ?></span></div>
      <div class="metric-grid"><article><span>All reports</span><strong><?= number_format($metrics['total'] ?? 0) ?></strong><small>In your department scope</small></article><article><span>Awaiting review</span><strong><?= number_format($metrics['awaiting_review'] ?? 0) ?></strong><small>Need verification</small></article><article><span>Active work</span><strong><?= number_format($metrics['active_work'] ?? 0) ?></strong><small>Assigned or in progress</small></article><article class="metric-highlight"><span>Community fast-track</span><strong><?= number_format($metrics['fast_track'] ?? 0) ?></strong><small>Prioritized by citizen support</small></article></div>
      <?php if ($adminNotices): ?><section class="admin-notice"><div><strong>New activity</strong><span>Recent notifications for your account</span></div><ul><?php foreach ($adminNotices as $notice): ?><li><span><strong><?= h($notice['title']) ?></strong><small><?= h($notice['message']) ?> · <?= h(pretty_date($notice['created_at'])) ?></small></span><div class="notice-actions"><?php if ($notice['complaint_id']): ?><a href="<?= h(admin_url('case', ['id' => $notice['complaint_id']])) ?>">Review →</a><?php endif; ?><form method="post" action="<?= h(url('actions.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="read_notification"><input type="hidden" name="notification_id" value="<?= (int)$notice['notification_id'] ?>"><button class="icon-button" type="submit" aria-label="Mark notification as read">✓</button></form></div></li><?php endforeach; ?></ul></section><?php endif; ?>
      <section class="panel queue-panel">
        <div class="queue-header"><div><p class="eyebrow">Department work</p><h2><?= $filter === 'open' ? 'Reports needing attention' : ucfirst($filter) . ' reports' ?></h2></div><nav class="filter-tabs" aria-label="Queue filters"><a class="<?= $filter === 'open' ? 'is-active' : '' ?>" href="<?= h(admin_url('dashboard', ['filter' => 'open'])) ?>">Open</a><a class="<?= $filter === 'all' ? 'is-active' : '' ?>" href="<?= h(admin_url('dashboard', ['filter' => 'all'])) ?>">All</a><a class="<?= $filter === 'resolved' ? 'is-active' : '' ?>" href="<?= h(admin_url('dashboard', ['filter' => 'resolved'])) ?>">Resolved</a><a class="<?= $filter === 'rejected' ? 'is-active' : '' ?>" href="<?= h(admin_url('dashboard', ['filter' => 'rejected'])) ?>">Rejected</a></nav></div>
        <?php if ($rows): ?>
          <div class="table-wrap"><table class="queue-table"><thead><tr><th>Issue</th><th>Department</th><th>Community</th><th>Status</th><th>Received</th><th></th></tr></thead><tbody>
          <?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['title']) ?></strong><small><?= h($row['complaint_id']) ?> · <?= h($row['category']) ?></small></td><td><?= h($row['department_name']) ?></td><td><?php if ((int)$row['community_validated']): ?><span class="fast-track-tag">Fast-track</span><?php else: ?><span><?= (int)$row['vote_count'] ?> votes</span><?php endif; ?></td><td><span class="status-pill <?= h(status_class($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= h(date('M j, Y', strtotime($row['submitted_at']))) ?></td><td><a class="button button-small button-outline" href="<?= h(admin_url('case', ['id' => $row['complaint_id']])) ?>">Review</a></td></tr><?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?><div class="empty-inline"><strong>Nothing in this queue.</strong><span>New reports and department updates will show here.</span></div><?php endif; ?>
      </section>
    <?php elseif ($view === 'case'): ?>
      <?php
      $complaintId = strtoupper(get_string('id', 32));
      $complaint = $complaintId !== '' ? find_complaint($complaintId) : null;
      if (!$complaint || !can_access_department($admin, (int)$complaint['department_id'])):
      ?>
        <div class="empty-panel"><strong>That report isn't available to this account.</strong><a class="text-link" href="<?= h(admin_url('dashboard')) ?>">Back to the report queue →</a></div>
      <?php else:
        $events = complaint_timeline($complaintId);
        $evidence = complaint_evidence($complaintId);
        $workStatement = db()->prepare('SELECT * FROM work_orders WHERE complaint_id = ?');
        $workStatement->execute([$complaintId]);
        $workOrder = $workStatement->fetch() ?: null;
        $verificationStatement = db()->prepare('SELECT validity_score, verification_status, remarks, verified_at FROM verifications WHERE complaint_id = ? ORDER BY verified_at DESC LIMIT 5');
        $verificationStatement->execute([$complaintId]);
        $verificationRows = $verificationStatement->fetchAll();
        $threshold = (int)setting('validity_score_threshold', '3');
      ?>
      <a class="back-link" href="<?= h(admin_url('dashboard')) ?>">← Back to report queue</a>
      <div class="case-heading"><div><p class="eyebrow"><?= h($complaint['category']) ?> <span>·</span> <?= h($complaint['department_name']) ?></p><h1><?= h($complaint['title']) ?></h1><p><?= h($complaint['location_address']) ?> <span>·</span> <?= h($complaint['complaint_id']) ?></p></div><span class="status-pill <?= h(status_class($complaint['status'])) ?>"><?= h($complaint['status']) ?></span></div>
      <?php if ((int)$complaint['community_validated']): ?><div class="fast-track-banner"><strong>Community fast-track reached</strong><span><?= (int)$complaint['vote_count'] ?> citizens support this report. It is prioritized for officer verification; an officer score is still required before a work order can be dispatched.</span></div><?php endif; ?>
      <div class="case-grid">
        <div class="case-main">
          <section class="panel"><div class="panel-heading"><span class="panel-step">01</span><div><h2>Citizen report</h2><p>Submitted <?= h(pretty_date($complaint['submitted_at'])) ?> by <?= h($complaint['reporter_name']) ?></p></div></div><p class="case-description"><?= nl2br(h($complaint['description'])) ?></p><div class="case-meta"><span><small>Category</small><strong><?= h($complaint['category']) ?></strong></span><span><small>Votes</small><strong><?= (int)$complaint['vote_count'] ?></strong></span><?php if ($complaint['latitude'] && $complaint['longitude']): ?><span><small>Coordinates</small><strong><?= h((string)$complaint['latitude']) ?>, <?= h((string)$complaint['longitude']) ?></strong></span><?php endif; ?></div><?php if ($evidence): ?><div class="evidence-list"><h3>Citizen evidence</h3><?php foreach ($evidence as $file): ?><a href="<?= h(url('file.php?type=evidence&id=' . (int)$file['evidence_id'])) ?>"><?= h($file['original_name']) ?> <span><?= number_format((int)$file['file_size'] / 1024) ?> KB</span></a><?php endforeach; ?></div><?php endif; ?></section>
          <?php if ($complaint['status'] === 'Submitted'): ?><form class="panel action-panel" method="post" action="<?= h(url('actions.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="admin_start_review"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><div><p class="eyebrow">Review step 1</p><h2>Begin verification</h2><p>Move this report into the department review queue and notify the citizen.</p></div><button class="button button-primary" type="submit">Start review <span aria-hidden="true">→</span></button></form><?php endif; ?>
          <?php if (in_array($complaint['status'], ['Submitted', 'Under Verification'], true)): ?><section class="panel action-panel"><div><p class="eyebrow">Review step 2</p><h2>Record validity</h2><p>Scores of <?= $threshold ?>–5 create a work order. Lower scores are rejected and require a reason.</p></div><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_verify"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><label>Validity score<select name="validity_score" required><option value="">Choose 1–5</option><?php for ($score = 5; $score >= 1; $score--): ?><option value="<?= $score ?>"><?= $score ?><?= $score >= $threshold ? ' · verified' : ' · below threshold' ?></option><?php endfor; ?></select></label><label>Verification notes <span class="optional-label">reason required for a low score</span><textarea name="remarks" maxlength="500" rows="3" placeholder="Record what was checked or why this report is invalid."></textarea></label><button class="button button-primary" type="submit">Save verification</button></form></section><?php endif; ?>
          <?php if (in_array($complaint['status'], ['Work Assigned', 'In Progress'], true)): ?><section class="panel action-panel"><div><p class="eyebrow">Field work</p><h2>Update the work order</h2><p>Only officers in this department can update this assignment.</p></div><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_update_work"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><label>Field crew or assignment<input name="field_crew" maxlength="140" value="<?= h($workOrder['field_crew'] ?? '') ?>" placeholder="Crew or team name"></label><label>Expected completion<input type="date" name="expected_completion" value="<?= h($workOrder['expected_completion'] ?? '') ?>"></label><button class="button button-primary" type="submit">Save and mark in progress</button></form></section><section class="panel action-panel"><div><p class="eyebrow">Completion</p><h2>Publish resolution proof</h2><p>Upload a completion photo and a short note. The public feed and citizen tracking page will update.</p></div><form method="post" action="<?= h(url('actions.php')) ?>" enctype="multipart/form-data" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_resolve"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><label>Work completed and outcome<textarea name="resolution_notes" required minlength="10" maxlength="2000" rows="4" placeholder="Describe what the field team completed."></textarea></label><label class="upload-field">Completion photo <span class="optional-label">JPG, PNG, or WebP · up to 8 MB</span><input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label><button class="button button-primary" type="submit">Mark resolved and publish</button></form></section><?php endif; ?>
          <?php if ($complaint['status'] === 'Rejected'): ?><section class="panel rejection-panel"><p class="eyebrow">Rejected report</p><h2>Reason recorded</h2><p><?= h($complaint['rejection_reason'] ?? 'No additional reason was provided.') ?></p></section><?php endif; ?>
        </div>
        <aside class="case-side">
          <section class="panel"><p class="eyebrow">Report details</p><div class="detail-stack"><div><small>Current status</small><strong><span class="status-pill <?= h(status_class($complaint['status'])) ?>"><?= h($complaint['status']) ?></span></strong></div><div><small>Citizen</small><strong><?= h($complaint['reporter_name']) ?></strong></div><div><small>Department</small><strong><?= h($complaint['department_name']) ?></strong></div><div><small>Validity score</small><strong><?= $complaint['validity_score'] === null ? 'Not rated' : (int)$complaint['validity_score'] . ' / 5' ?></strong></div><div><small>Community support</small><strong><?= (int)$complaint['vote_count'] ?> vote<?= (int)$complaint['vote_count'] === 1 ? '' : 's' ?></strong></div></div></section>
          <section class="panel"><p class="eyebrow">Activity timeline</p><?php if ($events): ?><ol class="timeline-list timeline-compact"><?php foreach ($events as $event): ?><li class="timeline-event"><span class="timeline-dot"></span><div><strong><?= h($event['activity_type']) ?></strong><p><?= h($event['log_description']) ?></p><time><?= h(pretty_date($event['created_at'])) ?></time></div></li><?php endforeach; ?></ol><?php else: ?><p class="muted">No updates yet.</p><?php endif; ?></section>
          <?php if ($verificationRows): ?><section class="panel"><p class="eyebrow">Verification history</p><?php foreach ($verificationRows as $verification): ?><div class="verification-row"><strong><?= (int)$verification['validity_score'] ?> / 5 <span><?= h($verification['verification_status']) ?></span></strong><p><?= h($verification['remarks'] ?? 'No additional notes.') ?></p><time><?= h(pretty_date($verification['verified_at'])) ?></time></div><?php endforeach; ?></section><?php endif; ?>
        </aside>
      </div>
      <?php endif; ?>
    <?php elseif ($view === 'settings'): ?>
      <?php if (!is_system_admin()): ?><div class="empty-panel"><strong>System administrator access is required.</strong><a href="<?= h(admin_url('dashboard')) ?>">Return to your queue →</a></div><?php else: ?>
      <div class="admin-page-heading"><div><p class="eyebrow"><span class="eyebrow-dot"></span> Platform configuration</p><h1>System settings</h1><p>Set verification and community support thresholds for the whole portal.</p></div></div>
      <section class="panel settings-panel"><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_settings"><label>Community fast-track votes<input type="number" min="2" max="100" name="community_vote_threshold" value="<?= h(setting('community_vote_threshold', '5')) ?>" required><span class="field-hint">At this number of unique citizen votes, the report is prioritized for officer verification. It does not replace an officer's validity score.</span></label><label>Minimum validity score to create a work order<input type="number" min="1" max="5" name="validity_score_threshold" value="<?= h(setting('validity_score_threshold', '3')) ?>" required><span class="field-hint">A report below this score is rejected and cannot be dispatched.</span></label><div class="setting-toggle"><div><strong>Email notifications</strong><small>Email provider is not configured in this project.</small></div><label class="switch"><input type="checkbox" name="email_notifications_enabled" value="1" <?= setting('email_notifications_enabled', '0') === '1' ? 'checked' : '' ?>><span></span></label></div><div class="setting-toggle"><div><strong>SMS gateway</strong><small>SMS provider is listed as a future decision in the SRS.</small></div><label class="switch"><input type="checkbox" name="sms_gateway_enabled" value="1" <?= setting('sms_gateway_enabled', '0') === '1' ? 'checked' : '' ?>><span></span></label></div><button class="button button-primary" type="submit">Save settings</button></form></section>
      <?php endif; ?>
    <?php elseif ($view === 'officers'): ?>
      <?php if (!is_system_admin()): ?><div class="empty-panel"><strong>System administrator access is required.</strong><a href="<?= h(admin_url('dashboard')) ?>">Return to your queue →</a></div><?php else:
        $departments = db()->query('SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name')->fetchAll();
        $officers = db()->query("SELECT u.full_name, u.email, u.created_at, d.department_name FROM users u JOIN departments d ON d.department_id = u.department_id WHERE u.role = 'department_admin' ORDER BY d.department_name, u.full_name")->fetchAll();
      ?>
      <div class="admin-page-heading"><div><p class="eyebrow"><span class="eyebrow-dot"></span> Access management</p><h1>Department officers</h1><p>Create officer accounts with access scoped to one department.</p></div></div>
      <div class="officers-layout"><section class="panel"><p class="eyebrow">New officer</p><h2>Add a department account</h2><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="admin_create_officer"><label>Full name<input name="full_name" required maxlength="120"></label><label>Email address<input name="email" type="email" required maxlength="190"></label><label>Department<select name="department_id" required><option value="">Choose a department</option><?php foreach ($departments as $department): ?><option value="<?= (int)$department['department_id'] ?>"><?= h($department['department_name']) ?></option><?php endforeach; ?></select></label><label>Temporary password<input name="password" type="password" required minlength="10" autocomplete="new-password"></label><label>Department access code<input name="access_code" type="password" required minlength="6" autocomplete="off"></label><button class="button button-primary" type="submit">Create officer account</button></form></section><section class="panel"><p class="eyebrow">Current access</p><h2><?= count($officers) ?> department officer<?= count($officers) === 1 ? '' : 's' ?></h2><?php if ($officers): ?><div class="officer-list"><?php foreach ($officers as $officer): ?><article><span class="officer-avatar"><?= h(strtoupper(substr($officer['full_name'], 0, 1))) ?></span><div><strong><?= h($officer['full_name']) ?></strong><small><?= h($officer['department_name']) ?></small><small><?= h($officer['email']) ?></small></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-inline"><strong>No department officers yet.</strong><span>Add an officer to enable department-specific verification.</span></div><?php endif; ?></section></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php require dirname(__DIR__) . '/app/views/partials/footer.php'; ?>
