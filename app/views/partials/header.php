<?php
$activePage = $activePage ?? '';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123c3a">
  <title><?= h($pageTitle ?? APP_NAME) ?> · CityRemedy</title>
  <meta name="description" content="Report local civic issues, follow each update, and see how your city is responding.">
  <link rel="stylesheet" href="<?= h(url('assets/app.css')) ?>">
  <script defer src="<?= h(url('assets/app.js')) ?>"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="<?= h(page_url('home')) ?>" aria-label="CityRemedy home">
      <span class="brand-mark" aria-hidden="true">CR</span>
      <span class="brand-copy"><strong>CityRemedy</strong><small>Better streets, together.</small></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav class="site-nav" id="site-nav" aria-label="Main navigation">
      <a class="<?= $activePage === 'home' ? 'is-active' : '' ?>" href="<?= h(page_url('home')) ?>">Overview</a>
      <a class="<?= $activePage === 'feed' ? 'is-active' : '' ?>" href="<?= h(page_url('feed')) ?>">Resolved work</a>
      <a class="<?= $activePage === 'track' ? 'is-active' : '' ?>" href="<?= h(page_url('track')) ?>">Track a report</a>
      <?php if ($user && $user['role'] === 'citizen'): ?>
        <a href="<?= h(page_url('report')) ?>">Report an issue</a>
        <a href="<?= h(page_url('account')) ?>">My account</a>
      <?php elseif ($user && in_array($user['role'], ['department_admin', 'system_admin'], true)): ?>
        <a href="<?= h(admin_url('dashboard')) ?>">Officer dashboard</a>
      <?php else: ?>
        <a href="<?= h(page_url('login')) ?>">Sign in</a>
      <?php endif; ?>
    </nav>
    <div class="header-action">
      <?php if ($user): ?>
        <form method="post" action="<?= h(url('actions.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="logout">
          <button class="button button-quiet button-small" type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <a class="button button-primary button-small" href="<?= h(page_url('register')) ?>">Join CityRemedy</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main id="main-content" class="page-shell">
  <?php foreach (pull_flashes() as $message): ?>
    <div class="flash flash-<?= h($message['type']) ?>" role="status"><?= h($message['message']) ?></div>
  <?php endforeach; ?>
