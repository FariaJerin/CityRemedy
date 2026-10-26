<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$adminCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'system_admin'")->fetchColumn();
$setupKey = (string)($config['setup_key'] ?? '');
$setupEnabled = strlen($setupKey) >= 32;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($adminCount > 0) {
        $error = 'The initial setup is already complete. Sign in from the officer portal.';
    } elseif ($setupEnabled && !hash_equals($setupKey, (string)($_POST['setup_key'] ?? ''))) {
        $error = 'The setup key is incorrect.';
    } elseif (!$setupEnabled) {
        $error = 'Initial setup is disabled. Configure a setup key in config.local.php.';
    } else {
        $name = post_string('full_name', 120);
        $email = mb_strtolower(post_string('email', 190));
        $password = (string)($_POST['password'] ?? '');
        $accessCode = (string)($_POST['access_code'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 10 || strlen($accessCode) < 6) {
            $error = 'Use a valid name and email, a password of at least 10 characters, and an access code of at least 6 characters.';
        } else {
            try {
                $statement = db()->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, department_id, access_code_hash)
                     VALUES (?, ?, ?, 'system_admin', NULL, ?)"
                );
                $statement->execute([
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    password_hash($accessCode, PASSWORD_DEFAULT),
                ]);
                flash('success', 'System administrator created. Sign in with your password and access code.');
                redirect_to(admin_url('login'));
            } catch (PDOException $exception) {
                log_error($exception);
                $error = $exception->getCode() === '23000'
                    ? 'An account already uses that email address.'
                    : 'The account could not be created. Check the database connection and try again.';
            }
        }
    }
}
$pageTitle = 'Initial system setup';
$activePage = '';
require dirname(__DIR__) . '/app/views/partials/header.php';
?>
<section class="setup-layout">
  <div class="setup-copy"><p class="eyebrow"><span class="eyebrow-dot"></span> Initial setup</p><h1>Set up your CityRemedy administrator.</h1><p>Create the first system administrator after importing the database. A setup key configured by the operator is required, and this page disables itself after setup is complete.</p><div class="setup-callout"><strong>Do not share these credentials.</strong><span>The access code adds a second check to the officer sign-in process.</span></div></div>
  <div class="auth-card">
    <?php if ($adminCount > 0): ?>
      <div class="setup-complete"><span class="completion-check">✓</span><h2>Setup is complete.</h2><p>The initial administrator already exists. Sign in to continue.</p><a class="button button-primary button-wide" href="<?= h(admin_url('login')) ?>">Go to officer sign in</a></div>
    <?php else: ?>
      <p class="eyebrow">System administrator</p><h2>Create the first officer account</h2>
      <?php if ($error !== ''): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
      <?php if (!$setupEnabled): ?>
        <div class="flash flash-error" role="alert">Initial setup is disabled. Configure a setup key in config.local.php to continue.</div>
      <?php else: ?>
        <form method="post" class="form-stack"><?= csrf_field() ?><label>Setup key<input name="setup_key" type="password" required autocomplete="off"></label><label>Full name<input name="full_name" required maxlength="120" autocomplete="name"></label><label>Email address<input name="email" type="email" required maxlength="190" autocomplete="email"></label><label>Password<input name="password" type="password" required minlength="10" autocomplete="new-password"></label><label>Officer access code<input name="access_code" type="password" required minlength="6" autocomplete="off"><span class="field-hint">Use a separate code with at least 6 characters.</span></label><button class="button button-primary button-wide" type="submit">Create administrator <span aria-hidden="true">→</span></button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/app/views/partials/footer.php'; ?>
