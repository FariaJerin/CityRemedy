<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE_URL . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function page_url(string $page, array $params = []): string
{
    return url('index.php?' . http_build_query(['page' => $page] + $params));
}

function admin_url(string $view = 'dashboard', array $params = []): string
{
    return url('admin.php?' . http_build_query(['view' => $view] + $params));
}

function redirect_to(string $destination): never
{
    header('Location: ' . $destination, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function safe_return_to(string $fallback): string
{
    $candidate = (string)($_POST['return_to'] ?? '');
    if ($candidate !== '' && str_starts_with($candidate, APP_BASE_URL . '/')) {
        return $candidate;
    }
    return $fallback;
}

function status_class(string $status): string
{
    return match ($status) {
        'Submitted' => 'status-submitted',
        'Under Verification' => 'status-review',
        'Work Assigned' => 'status-assigned',
        'In Progress' => 'status-progress',
        'Resolved', 'Closed' => 'status-resolved',
        'Rejected' => 'status-rejected',
        default => 'status-neutral',
    };
}

function pretty_date(?string $date): string
{
    if (!$date) {
        return 'Not yet available';
    }
    return date('M j, Y · g:i A', strtotime($date));
}

function setting(string $key, string $fallback = ''): string
{
    $statement = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $fallback : (string)$value;
}

function notify_user(int $userId, ?string $complaintId, string $title, string $message): void
{
    $statement = db()->prepare(
        'INSERT INTO notifications (user_id, complaint_id, title, message) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$userId, $complaintId, $title, $message]);
}

function log_activity(string $complaintId, ?int $actorId, string $type, string $description): void
{
    $statement = db()->prepare(
        'INSERT INTO activity_logs (complaint_id, actor_id, activity_type, log_description)
         VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$complaintId, $actorId, $type, $description]);
}

function log_error(Throwable $error): void
{
    error_log('[CityRemedy] ' . $error->getMessage());
}

function post_string(string $key, int $maxLength = 2000): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value) > $maxLength) {
        return mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function get_string(string $key, int $maxLength = 200): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
}

function save_uploaded_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file could not be uploaded. Please try again.'];
    }
    if ((int)($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Each file must be 8 MB or smaller.'];
    }
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        return ['ok' => false, 'error' => 'The uploaded file could not be verified.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, WebP, or PDF file.'];
    }

    if (!is_dir(UPLOAD_DIRECTORY) && !mkdir(UPLOAD_DIRECTORY, 0770, true) && !is_dir(UPLOAD_DIRECTORY)) {
        return ['ok' => false, 'error' => 'The upload folder is not writable. Check the XAMPP folder permissions.'];
    }
    $storedName = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    $destination = UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'The file could not be saved. Check the XAMPP folder permissions.'];
    }

    return [
        'ok' => true,
        'file' => [
            'stored_name' => $storedName,
            'original_name' => mb_substr(basename((string)$file['name']), 0, 255),
            'mime_type' => $mime,
            'file_size' => (int)$file['size'],
        ],
    ];
}

function uploaded_file_list(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $files === [] ? [] : [$files];
    }
    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $normalized;
}

function add_evidence(string $complaintId, array $file): void
{
    $statement = db()->prepare(
        'INSERT INTO evidence_files (complaint_id, original_name, stored_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?)'
    );
    $statement->execute([
        $complaintId,
        $file['original_name'],
        $file['stored_name'],
        $file['mime_type'],
        $file['file_size'],
    ]);
}
