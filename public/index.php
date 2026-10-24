<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = get_string('page', 40) ?: 'home';
$allowedPages = ['home', 'report', 'track', 'login', 'register', 'feed', 'account', 'matches', 'track-status'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'not_found';
}

if ($page === 'matches') {
    header('Content-Type: application/json; charset=utf-8');
    $category = get_string('category', 60);
    $location = get_string('location', 255);
    echo json_encode(similar_complaints($category, $location), JSON_UNESCAPED_SLASHES);
    exit;
}
if ($page === 'track-status') {
    header('Content-Type: application/json; charset=utf-8');
    $id = strtoupper(get_string('id', 32));
    $complaint = $id === '' ? null : find_complaint($id);
    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found.']);
        exit;
    }
    echo json_encode([
        'complaint_id' => $complaint['complaint_id'],
        'status' => $complaint['status'],
        'updated_at' => $complaint['updated_at'],
        'timeline' => complaint_timeline($id),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$user = current_user();
if ($page === 'report' && !is_citizen()) {
    flash('error', 'Sign in or create a citizen account before submitting a report.');
    redirect_to(page_url('login'));
}
if ($page === 'account' && !is_citizen()) {
    flash('error', 'Sign in to see your reports.');
    redirect_to(page_url('login'));
}

$pageTitle = match ($page) {
    'home' => 'Your city, heard',
    'report' => 'Report a civic issue',
    'track' => 'Track a report',
    'login' => 'Citizen sign in',
    'register' => 'Create a citizen account',
    'feed' => 'Resolved work',
    'account' => 'My account',
    default => 'Page not found',
};
$activePage = $page === 'account' ? 'account' : $page;
require dirname(__DIR__) . '/app/views/partials/header.php';
?>
<?php if ($page === 'home'): ?>
  <?php
  $stats = public_stats();
  $resolutions = public_resolutions(3);
  $user = current_user();
  ?>
  <section class="hero hero-home">
    <div class="hero-copy">
      <p class="eyebrow"><span class="eyebrow-dot"></span> Your neighborhood, in motion</p>
      <h1>See a problem.<br><em>Start the fix.</em></h1>
      <p class="hero-lede">A clearer way to report local issues, follow the work, and make sure the right city team sees it.</p>
      <div class="hero-actions">
        <?php if (is_citizen()): ?>
          <a class="button button-primary" href="<?= h(page_url('report')) ?>">Report an issue <span aria-hidden="true">→</span></a>
        <?php elseif (is_admin()): ?>
          <a class="button button-primary" href="<?= h(admin_url('dashboard')) ?>">Officer dashboard <span aria-hidden="true">→</span></a>
        <?php else: ?>
          <a class="button button-primary" href="<?= h(page_url('register')) ?>">Get started <span aria-hidden="true">→</span></a>
        <?php endif; ?>
        <a class="button button-outline" href="<?= h(page_url('track')) ?>">Track a report</a>
      </div>
      <div class="hero-note"><span class="note-icon">01</span><span>Every report gets a unique ID and a visible timeline.</span></div>
    </div>
    <div class="hero-art" aria-label="CityRemedy issue reporting overview">
      <div class="sun-disc"></div>
      <div class="city-silhouette city-back"></div>
      <div class="city-silhouette city-front"></div>
      <div class="route-line"><span></span><span></span><span></span></div>
      <div class="map-pin pin-one"><i></i></div>
      <div class="map-pin pin-two"><i></i></div>
      <div class="hero-callout callout-one"><span class="callout-icon">✓</span><span><strong>Team notified</strong><small>Road Maintenance</small></span></div>
      <div class="hero-callout callout-two"><span class="callout-avatar">CR</span><span><strong>Report received</strong><small>Tracking ID assigned</small></span></div>
      <div class="art-caption"><span>LOCAL ISSUES</span><span>VISIBLE PROGRESS</span><span>COMMUNITY POWER</span></div>
    </div>
  </section>
  <section class="stats-strip" aria-label="CityRemedy activity">
    <div class="stat-item"><strong><?= number_format($stats['total']) ?></strong><span>reports received</span></div>
    <div class="stat-item"><strong><?= number_format($stats['reviewing']) ?></strong><span>under review</span></div>
    <div class="stat-item"><strong><?= number_format($stats['active']) ?></strong><span>in the field</span></div>
    <div class="stat-item"><strong><?= number_format($stats['resolved']) ?></strong><span>issues resolved</span></div>
  </section>
  <section class="section-block section-how">
    <div class="section-heading">
      <div><p class="eyebrow">A better way to get things done</p><h2>From street-level<br>problem to real progress.</h2></div>
      <p>CityRemedy connects a clear report to the department that can act on it—and keeps you informed at each step.</p>
    </div>
    <div class="steps-grid">
      <article class="step-card"><span class="step-number">01</span><div class="step-icon icon-report">+</div><h3>Share what you see</h3><p>Add the issue, location, and a photo so the right team has the details they need.</p></article>
      <article class="step-card"><span class="step-number">02</span><div class="step-icon icon-route">↗</div><h3>Reach the right team</h3><p>Your report is routed to the department responsible for that kind of work.</p></article>
      <article class="step-card"><span class="step-number">03</span><div class="step-icon icon-progress">→</div><h3>Follow every update</h3><p>Use your tracking ID to see verification, work assignment, and resolution proof.</p></article>
    </div>
  </section>
  <section class="section-block section-feed">
    <div class="section-heading section-heading-compact">
      <div><p class="eyebrow">Proof of progress</p><h2>Recently resolved</h2></div>
      <a class="text-link" href="<?= h(page_url('feed')) ?>">See all resolved work <span aria-hidden="true">→</span></a>
    </div>
    <?php if ($resolutions): ?>
      <div class="resolution-grid">
        <?php foreach ($resolutions as $item): ?>
          <article class="resolution-card">
            <div class="resolution-image">
              <img src="<?= h(url('file.php?type=resolution&id=' . rawurlencode($item['complaint_id']))) ?>" alt="Proof of completed work">
              <span class="status-pill <?= h(status_class($item['status'])) ?>"><?= h($item['status']) ?></span>
            </div>
            <div class="resolution-content">
              <p class="card-kicker"><?= h($item['category']) ?> <span>·</span> <?= h($item['department_name']) ?></p>
              <h3><?= h($item['title']) ?></h3>
              <p class="muted"><?= h($item['location_address']) ?></p>
              <div class="card-bottom"><span>Resolved <?= h(date('M j, Y', strtotime($item['resolved_at']))) ?></span><a href="<?= h(page_url('track', ['id' => $item['complaint_id']])) ?>">View report →</a></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-panel"><strong>Resolved work will appear here.</strong><span>Once a department posts completion proof, the update is shared publicly.</span></div>
    <?php endif; ?>
  </section>
  <section class="cta-band">
    <div><p class="eyebrow">Make your block count</p><h2>Small reports can lead to visible change.</h2></div>
    <a class="button button-light" href="<?= h(is_citizen() ? page_url('report') : (is_admin() ? admin_url('dashboard') : page_url('register'))) ?>"><?= is_admin() ? 'Open officer dashboard' : 'Start a report' ?> <span aria-hidden="true">→</span></a>
  </section>
<?php elseif ($page === 'register' || $page === 'login'): ?>
  <?php $isRegister = $page === 'register'; ?>
  <section class="auth-layout">
    <div class="auth-story">
      <p class="eyebrow"><span class="eyebrow-dot"></span> Citizen portal</p>
      <h1><?= $isRegister ? 'Your voice can move things forward.' : 'Welcome back to your neighborhood.' ?></h1>
      <p><?= $isRegister ? 'Create an account to report issues, support existing reports, and follow every update.' : 'Sign in to manage your reports and keep up with the issues you care about.' ?></p>
      <div class="auth-promise"><span>01</span><div><strong>One place for the whole journey</strong><small>From first report to work completed.</small></div></div>
      <div class="auth-promise"><span>02</span><div><strong>Clear updates, no guesswork</strong><small>Every report has a unique tracking ID.</small></div></div>
    </div>
    <div class="auth-card">
      <p class="eyebrow"><?= $isRegister ? 'Create your account' : 'Citizen sign in' ?></p>
      <h2><?= $isRegister ? 'Join CityRemedy' : 'Good to see you again.' ?></h2>
      <p class="muted"><?= $isRegister ? 'It only takes a moment to get started.' : 'Use the email and password for your citizen account.' ?></p>
      <form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $isRegister ? 'register' : 'citizen_login' ?>">
        <?php if ($isRegister): ?>
          <label>Full name<input name="full_name" required maxlength="120" autocomplete="name" placeholder="Your name"></label>
        <?php endif; ?>
        <label>Email address<input name="email" type="email" required maxlength="190" autocomplete="email" placeholder="you@example.com"></label>
        <label>Password<input name="password" type="password" required minlength="<?= $isRegister ? '10' : '1' ?>" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" placeholder="<?= $isRegister ? 'At least 10 characters' : 'Your password' ?>"></label>
        <?php if ($isRegister): ?>
          <label class="check-row"><input type="checkbox" name="privacy_ack" value="1" required><span>I understand that reports may be visible to the public. My contact details will not be shown.</span></label>
        <?php endif; ?>
        <button class="button button-primary button-wide" type="submit"><?= $isRegister ? 'Create citizen account' : 'Sign in' ?> <span aria-hidden="true">→</span></button>
      </form>
      <p class="auth-switch"><?= $isRegister ? 'Already have an account?' : 'New to CityRemedy?' ?> <a href="<?= h(page_url($isRegister ? 'login' : 'register')) ?>"><?= $isRegister ? 'Sign in' : 'Create an account' ?></a></p>
      <a class="officer-link" href="<?= h(admin_url('login')) ?>">Municipal officer? Sign in here</a>
    </div>
  </section>
<?php elseif ($page === 'report'): ?>
  <?php
  $selectedCategory = get_string('category', 60);
  ?>
  <section class="page-intro">
    <p class="eyebrow"><span class="eyebrow-dot"></span> Citizen report</p>
    <h1>Tell us what needs attention.</h1>
    <p>Share a clear description and location. Your report will be routed to the relevant city department.</p>
  </section>
  <div class="report-layout">
    <section class="panel form-panel">
      <div class="panel-heading"><span class="panel-step">01</span><div><h2>Issue details</h2><p>Fields marked with * are required.</p></div></div>
      <form method="post" action="<?= h(url('actions.php')) ?>" enctype="multipart/form-data" class="form-stack" data-report-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_report">
        <label>Issue category *<select name="category" required data-category><option value="">Choose a category</option><?php foreach (categories() as $category): ?><option value="<?= h($category) ?>" <?= $category === $selectedCategory ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></label>
        <label>Short title *<input name="title" required maxlength="140" minlength="8" placeholder="For example, large pothole near the bus stop"></label>
        <label>What happened? *<textarea name="description" required minlength="20" maxlength="4000" rows="5" placeholder="Describe the problem and anything that could help the city team find it."></textarea></label>
        <label>Exact location or nearby landmark *<input name="location_address" required maxlength="255" minlength="4" placeholder="Street, block, nearby landmark"></label>
        <div class="match-panel" data-match-panel hidden aria-live="polite"></div>
        <div class="form-row">
          <label>Latitude <span class="optional-label">optional</span><input name="latitude" type="number" step="any" min="-90" max="90" placeholder="23.8103"></label>
          <label>Longitude <span class="optional-label">optional</span><input name="longitude" type="number" step="any" min="-180" max="180" placeholder="90.4125"></label>
        </div>
        <label class="upload-field">Photos or document evidence <span class="optional-label">up to 4 files · 8 MB each · JPG, PNG, WebP, PDF</span><input name="evidence[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"><span class="upload-hint">Choose files that help officers understand the issue. Photos stay private to you and authorized officers.</span></label>
        <label class="check-row"><input type="checkbox" name="is_anonymous" value="1"><span>Show my report publicly as “Community member”. My account is still used to send updates.</span></label>
        <label class="check-row"><input type="checkbox" name="confirm_not_duplicate" value="1" required><span>I checked the similar reports above and this is a different issue.</span></label>
        <div class="form-footer"><p>After submission, keep the tracking ID shown on the confirmation screen.</p><button class="button button-primary" type="submit">Submit report <span aria-hidden="true">→</span></button></div>
      </form>
    </section>
    <aside class="report-aside">
      <div class="aside-card aside-dark"><p class="eyebrow">Before you submit</p><h3>Help the right team act sooner.</h3><ul class="check-list"><li>Choose the closest category.</li><li>Include the nearest street or landmark.</li><li>Check for an existing report in the same place.</li><li>Never upload sensitive personal documents.</li></ul></div>
      <div class="aside-card"><span class="aside-icon">ID</span><h3>One report. One tracking ID.</h3><p>Use your ID to follow verification, work assignment, and resolution updates.</p></div>
    </aside>
  </div>
<?php elseif ($page === 'track'): ?>
  <?php
  $complaintId = strtoupper(get_string('id', 32));
  $complaint = $complaintId !== '' ? find_complaint($complaintId) : null;
  $timeline = $complaint ? complaint_timeline($complaintId) : [];
  $evidence = $complaint && current_user() && (
      is_admin() || (int)current_user()['user_id'] === (int)$complaint['citizen_id']
  ) ? complaint_evidence($complaintId) : [];
  $resolution = null;
  if ($complaint && in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
      $statement = db()->prepare('SELECT resolution_notes, resolved_at FROM resolutions WHERE complaint_id = ?');
      $statement->execute([$complaintId]);
      $resolution = $statement->fetch() ?: null;
  }
  $ownFeedback = $complaint && is_citizen()
      ? feedback_for_user($complaintId, (int)current_user()['user_id'])
      : null;
  ?>
  <section class="page-intro page-intro-row">
    <div><p class="eyebrow"><span class="eyebrow-dot"></span> Report tracking</p><h1>Follow the work, step by step.</h1><p>Enter the unique ID you received when the report was submitted.</p></div>
    <form method="get" action="<?= h(url('index.php')) ?>" class="track-search">
      <input type="hidden" name="page" value="track">
      <label class="sr-only" for="track-id">Complaint tracking ID</label>
      <input id="track-id" name="id" value="<?= h($complaintId) ?>" placeholder="CR-20261018-AB12CD34" maxlength="32" required>
      <button class="button button-primary" type="submit">Find report</button>
    </form>
  </section>
  <?php if ($complaintId !== '' && !$complaint): ?>
    <div class="empty-panel empty-error"><strong>We couldn't find that tracking ID.</strong><span>Check the letters and numbers, then try again.</span></div>
  <?php elseif ($complaint): ?>
  <section class="track-layout" data-track-id="<?= h($complaintId) ?>" data-track-url="<?= h(page_url('track-status')) ?>">
      <div class="panel track-main">
        <div class="track-topline"><div><p class="eyebrow"><?= h($complaint['category']) ?> <span>·</span> <?= h($complaint['department_name']) ?></p><h2><?= h($complaint['title']) ?></h2></div><span class="status-pill <?= h(status_class($complaint['status'])) ?>" data-live-status><?= h($complaint['status']) ?></span></div>
        <div class="track-id-row"><span>Tracking ID</span><strong><?= h($complaint['complaint_id']) ?></strong><button type="button" class="copy-button" data-copy="<?= h($complaint['complaint_id']) ?>">Copy ID</button></div>
        <p class="track-description"><?= nl2br(h($complaint['description'])) ?></p>
        <div class="detail-chips"><span><?= h($complaint['location_address']) ?></span><span>Reported <?= h(pretty_date($complaint['submitted_at'])) ?></span><span><?= h($complaint['reporter_name']) ?></span></div>
        <div class="timeline-heading"><h3>Report timeline</h3><span>Updates refresh automatically</span></div>
        <ol class="timeline-list" data-timeline>
          <?php foreach ($timeline as $event): ?>
            <li class="timeline-event"><span class="timeline-dot"></span><div><strong><?= h($event['activity_type']) ?></strong><p><?= h($event['log_description']) ?></p><time><?= h(pretty_date($event['created_at'])) ?></time></div></li>
          <?php endforeach; ?>
        </ol>
        <?php if (!$timeline): ?><p class="muted">A timeline update will appear after this report is reviewed.</p><?php endif; ?>
        <?php if ($resolution): ?><div class="resolution-note"><strong>Department update</strong><p><?= nl2br(h($resolution['resolution_notes'])) ?></p><span>Posted <?= h(pretty_date($resolution['resolved_at'])) ?></span><a href="<?= h(url('file.php?type=resolution&id=' . rawurlencode($complaintId))) ?>">View completion proof →</a></div><?php endif; ?>
        <?php if ($evidence): ?><div class="evidence-list"><h3>Your evidence files</h3><?php foreach ($evidence as $file): ?><a href="<?= h(url('file.php?type=evidence&id=' . (int)$file['evidence_id'])) ?>"><?= h($file['original_name']) ?> <span><?= h(strtoupper(pathinfo($file['original_name'], PATHINFO_EXTENSION))) ?></span></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <aside class="track-aside">
        <div class="aside-card"><p class="eyebrow">Current status</p><div class="status-large"><span class="status-pill <?= h(status_class($complaint['status'])) ?>" data-live-status><?= h($complaint['status']) ?></span></div><p data-last-updated>Last updated <?= h(pretty_date($complaint['updated_at'])) ?></p></div>
        <?php if (is_citizen() && (int)$complaint['citizen_id'] !== (int)current_user()['user_id'] && !in_array($complaint['status'], ['Resolved', 'Closed', 'Rejected'], true)): ?>
          <div class="aside-card aside-vote"><p class="eyebrow">Same issue on your street?</p><h3>Support this report.</h3><p><?= (int)$complaint['vote_count'] ?> community <?= (int)$complaint['vote_count'] === 1 ? 'vote' : 'votes' ?> so far. <?= (int)$complaint['vote_count'] >= (int)setting('community_vote_threshold', '5') ? 'This report has reached the community fast-track threshold.' : 'More support helps officers prioritize the review.' ?></p><form method="post" action="<?= h(url('actions.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="vote"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><button class="button button-outline button-wide" type="submit">Support this report</button></form></div>
        <?php elseif (is_citizen() && (int)$complaint['citizen_id'] === (int)current_user()['user_id'] && !in_array($complaint['status'], ['Resolved', 'Closed', 'Rejected'], true)): ?>
          <div class="aside-card"><span class="aside-icon">+</span><h3>Keep your report moving.</h3><p>Share the tracking ID with neighbors who have seen the same issue so they can support this report.</p></div>
        <?php endif; ?>
        <?php if (in_array($complaint['status'], ['Resolved', 'Closed'], true) && is_citizen() && (int)$complaint['citizen_id'] === (int)current_user()['user_id'] && !$ownFeedback && $complaint['status'] !== 'Closed'): ?>
          <div class="aside-card feedback-card"><p class="eyebrow">Your feedback matters</p><h3>Was the work done well?</h3><form method="post" action="<?= h(url('actions.php')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="feedback"><input type="hidden" name="complaint_id" value="<?= h($complaintId) ?>"><label>Rating<select name="star_rating" required><option value="">Choose a rating</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option><?php endfor; ?></select></label><label>Comment <span class="optional-label">optional</span><textarea name="comments" maxlength="1000" rows="3" placeholder="Share a short note about the result."></textarea></label><button class="button button-primary button-wide" type="submit">Send feedback</button></form></div>
        <?php elseif ($ownFeedback): ?><div class="aside-card"><p class="eyebrow">Thanks for weighing in</p><h3>Your feedback is recorded.</h3><p>You rated this work <?= (int)$ownFeedback['star_rating'] ?> out of 5.</p></div><?php endif; ?>
      </aside>
    </section>
  <?php else: ?>
    <div class="empty-panel"><strong>Have a tracking ID?</strong><span>Enter it above to see the report details and latest status.</span></div>
  <?php endif; ?>
<?php elseif ($page === 'feed'): ?>
  <?php $items = public_resolutions(30); ?>
  <section class="page-intro page-intro-row"><div><p class="eyebrow"><span class="eyebrow-dot"></span> Public resolution feed</p><h1>Work completed.<br>Proof included.</h1><p>See recently resolved civic reports and the updates departments have shared.</p></div><a class="button button-outline" href="<?= h(page_url('track')) ?>">Track a report</a></section>
  <?php if ($items): ?><div class="resolution-grid resolution-grid-wide"><?php foreach ($items as $item): ?><article class="resolution-card"><div class="resolution-image"><img src="<?= h(url('file.php?type=resolution&id=' . rawurlencode($item['complaint_id']))) ?>" alt="Completion proof for <?= h($item['title']) ?>"><span class="status-pill status-resolved"><?= h($item['status']) ?></span></div><div class="resolution-content"><p class="card-kicker"><?= h($item['category']) ?> <span>·</span> <?= h($item['department_name']) ?></p><h3><?= h($item['title']) ?></h3><p class="muted"><?= h($item['location_address']) ?></p><p><?= h($item['resolution_notes']) ?></p><div class="card-bottom"><span><?= h(pretty_date($item['resolved_at'])) ?></span><a href="<?= h(page_url('track', ['id' => $item['complaint_id']])) ?>">Full timeline →</a></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-panel"><strong>No completed work has been published yet.</strong><span>When departments upload completion proof, it will be shared here.</span></div><?php endif; ?>
<?php elseif ($page === 'account'): ?>
  <?php
  $statement = db()->prepare(
      'SELECT complaint_id, category, title, status, vote_count, submitted_at, updated_at
       FROM complaints WHERE citizen_id = ? ORDER BY submitted_at DESC LIMIT 100'
  );
  $statement->execute([(int)current_user()['user_id']]);
  $myReports = $statement->fetchAll();
  $noticeStatement = db()->prepare(
      'SELECT notification_id, complaint_id, title, message, is_read, created_at
       FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
  );
  $noticeStatement->execute([(int)current_user()['user_id']]);
  $myNotifications = $noticeStatement->fetchAll();
  ?>
  <section class="page-intro page-intro-row"><div><p class="eyebrow"><span class="eyebrow-dot"></span> Your CityRemedy account</p><h1>Hi, <?= h(current_user()['full_name']) ?>.</h1><p>All your reports and the latest department updates, in one place.</p></div><a class="button button-primary" href="<?= h(page_url('report')) ?>">New report <span aria-hidden="true">→</span></a></section>
  <div class="account-grid">
    <section class="panel"><div class="panel-heading"><span class="panel-step">01</span><div><h2>Your reports</h2><p><?= count($myReports) ?> total</p></div></div><?php if ($myReports): ?><div class="table-wrap"><table><thead><tr><th>Report</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody><?php foreach ($myReports as $report): ?><tr><td><strong><?= h($report['title']) ?></strong><small><?= h($report['complaint_id']) ?> · <?= h($report['category']) ?></small></td><td><span class="status-pill <?= h(status_class($report['status'])) ?>"><?= h($report['status']) ?></span></td><td><?= h(date('M j, Y', strtotime($report['submitted_at']))) ?></td><td><a class="text-link" href="<?= h(page_url('track', ['id' => $report['complaint_id']])) ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-inline"><strong>No reports yet.</strong><span>When you submit an issue, it will appear here.</span><a class="text-link" href="<?= h(page_url('report')) ?>">Create your first report →</a></div><?php endif; ?></section>
    <aside class="panel"><div class="panel-heading"><span class="panel-step">02</span><div><h2>Recent updates</h2><p>From your city teams</p></div></div><?php if ($myNotifications): ?><div class="notification-list"><?php foreach ($myNotifications as $notice): ?><article class="<?= (int)$notice['is_read'] ? '' : 'notification-unread' ?>"><span class="notification-dot"></span><div><strong><?= h($notice['title']) ?></strong><p><?= h($notice['message']) ?></p><time><?= h(pretty_date($notice['created_at'])) ?></time><?php if ($notice['complaint_id']): ?><a href="<?= h(page_url('track', ['id' => $notice['complaint_id']])) ?>">View report →</a><?php endif; ?></div><?php if (!(int)$notice['is_read']): ?><form method="post" action="<?= h(url('actions.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="read_notification"><input type="hidden" name="notification_id" value="<?= (int)$notice['notification_id'] ?>"><button class="icon-button" type="submit" aria-label="Mark notification as read">✓</button></form><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-inline"><strong>You're all caught up.</strong><span>Updates will appear here when a department changes your report.</span></div><?php endif; ?></aside>
  </div>
<?php else: ?>
  <section class="empty-panel"><strong>That page isn't here.</strong><a class="text-link" href="<?= h(page_url('home')) ?>">Back to CityRemedy home →</a></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/app/views/partials/footer.php'; ?>
