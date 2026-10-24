<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$type = get_string('type', 20);
$storedName = null;
$mime = null;
$originalName = 'CityRemedy file';

if ($type === 'resolution') {
    $complaintId = strtoupper(get_string('id', 32));
    $statement = db()->prepare(
        "SELECT r.proof_stored_name, r.proof_mime_type, r.proof_original_name
         FROM resolutions r JOIN complaints c ON c.complaint_id = r.complaint_id
         WHERE r.complaint_id = ? AND c.status IN ('Resolved', 'Closed')"
    );
    $statement->execute([$complaintId]);
    $file = $statement->fetch();
    if ($file) {
        $storedName = $file['proof_stored_name'];
        $mime = $file['proof_mime_type'];
        $originalName = $file['proof_original_name'];
    }
} elseif ($type === 'evidence') {
    $evidenceId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($evidenceId !== false && $evidenceId > 0) {
        $statement = db()->prepare(
            'SELECT e.stored_name, e.mime_type, e.original_name, c.citizen_id, c.department_id
             FROM evidence_files e JOIN complaints c ON c.complaint_id = e.complaint_id
             WHERE e.evidence_id = ?'
        );
        $statement->execute([$evidenceId]);
        $file = $statement->fetch();
        $user = current_user();
        $authorized = $file && $user && (
            ((int)$file['citizen_id'] === (int)$user['user_id'] && $user['role'] === 'citizen')
            || (is_admin() && can_access_department($user, (int)$file['department_id']))
        );
        if ($authorized) {
            $storedName = $file['stored_name'];
            $mime = $file['mime_type'];
            $originalName = $file['original_name'];
        } elseif ($file) {
            http_response_code(403);
            exit('You do not have permission to view this evidence file.');
        }
    }
}

$path = $storedName ? UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . basename($storedName) : '';
if (!$path || !is_file($path) || !is_string($mime)) {
    http_response_code(404);
    exit('File not found.');
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
$disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode(basename($originalName)) . '"');
readfile($path);
