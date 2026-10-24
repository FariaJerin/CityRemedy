<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$action = post_string('action', 60);

function action_fail(string $message, string $destination): never
{
    flash('error', $message);
    redirect_to($destination);
}

function action_succeed(string $message, string $destination): never
{
    flash('success', $message);
    redirect_to($destination);
}

try {
    switch ($action) {
        case 'register': {
            if (current_user()) {
                redirect_to(page_url('account'));
            }
            $name = post_string('full_name', 120);
            $email = mb_strtolower(post_string('email', 190));
            $password = (string)($_POST['password'] ?? '');
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                action_fail('Enter your name and a valid email address.', page_url('register'));
            }
            if (strlen($password) < 10) {
                action_fail('Choose a password with at least 10 characters.', page_url('register'));
            }
            if (($_POST['privacy_ack'] ?? '') !== '1') {
                action_fail('Please confirm the report privacy notice to continue.', page_url('register'));
            }
            $statement = db()->prepare(
                "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'citizen')"
            );
            try {
                $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    action_fail('An account already uses that email address. Sign in instead.', page_url('login'));
                }
                throw $error;
            }
            $userId = (int)db()->lastInsertId();
            login_user([
                'user_id' => $userId,
                'full_name' => $name,
                'email' => $email,
                'role' => 'citizen',
                'department_id' => null,
            ]);
            action_succeed('Your citizen account is ready.', page_url('account'));
        }

        case 'citizen_login': {
            $email = mb_strtolower(post_string('email', 190));
            $password = (string)($_POST['password'] ?? '');
            $statement = db()->prepare(
                "SELECT user_id, full_name, email, password_hash, role, department_id
                 FROM users WHERE email = ? AND role = 'citizen' LIMIT 1"
            );
            $statement->execute([$email]);
            $user = $statement->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                action_fail('That email and password combination could not be verified.', page_url('login'));
            }
            login_user($user);
            action_succeed('You are signed in.', page_url('account'));
        }

        case 'admin_login': {
            $email = mb_strtolower(post_string('email', 190));
            $password = (string)($_POST['password'] ?? '');
            $accessCode = (string)($_POST['access_code'] ?? '');
            $statement = db()->prepare(
                "SELECT u.user_id, u.full_name, u.email, u.password_hash, u.access_code_hash,
                        u.role, u.department_id, d.department_name
                 FROM users u LEFT JOIN departments d ON d.department_id = u.department_id
                 WHERE u.email = ? AND u.role IN ('department_admin', 'system_admin') LIMIT 1"
            );
            $statement->execute([$email]);
            $user = $statement->fetch();
            if (!$user
                || !password_verify($password, $user['password_hash'])
                || !is_string($user['access_code_hash'])
                || !password_verify($accessCode, $user['access_code_hash'])) {
                action_fail('Officer credentials could not be verified.', admin_url('login'));
            }
            login_user($user);
            action_succeed('Officer session started.', admin_url('dashboard'));
        }

        case 'logout': {
            logout_user();
            action_succeed('You have signed out.', page_url('home'));
        }

        case 'submit_report': {
            $citizen = require_citizen();
            $category = post_string('category', 60);
            $title = post_string('title', 140);
            $description = post_string('description', 4000);
            $location = post_string('location_address', 255);
            $latitudeText = post_string('latitude', 30);
            $longitudeText = post_string('longitude', 30);
            $back = page_url('report', ['category' => $category]);

            if (!in_array($category, categories(), true)
                || mb_strlen($title) < 8
                || mb_strlen($description) < 20
                || mb_strlen($location) < 4) {
                action_fail('Add a category, clear title, description, and specific location.', $back);
            }
            if (($_POST['confirm_not_duplicate'] ?? '') !== '1') {
                action_fail('Confirm that you checked for an existing report in the same place.', $back);
            }
            $latitude = $latitudeText === '' ? null : filter_var($latitudeText, FILTER_VALIDATE_FLOAT);
            $longitude = $longitudeText === '' ? null : filter_var($longitudeText, FILTER_VALIDATE_FLOAT);
            if (($latitudeText !== '' && ($latitude === false || $latitude < -90 || $latitude > 90))
                || ($longitudeText !== '' && ($longitude === false || $longitude < -180 || $longitude > 180))
                || (($latitudeText === '') !== ($longitudeText === ''))) {
                action_fail('Enter both valid map coordinates, or leave both fields blank.', $back);
            }

            $deptId = department_id_for(category_department_slug($category));
            if ($deptId === null) {
                throw new RuntimeException('The department for this category is not configured.');
            }
            $exact = db()->prepare(
                "SELECT complaint_id FROM complaints
                 WHERE category = ? AND LOWER(location_address) = LOWER(?)
                   AND status NOT IN ('Resolved', 'Closed', 'Rejected')
                 LIMIT 1"
            );
            $exact->execute([$category, $location]);
            $duplicateId = $exact->fetchColumn();
            if ($duplicateId !== false) {
                action_fail(
                    'There is already an active report for this category and exact location. Support the existing report instead.',
                    page_url('track', ['id' => (string)$duplicateId])
                );
            }

            $evidenceUploads = uploaded_file_list($_FILES['evidence'] ?? []);
            if (count($evidenceUploads) > 4) {
                action_fail('Choose up to 4 evidence files.', $back);
            }
            $uploadedFiles = [];
            foreach ($evidenceUploads as $evidenceUpload) {
                $uploadResult = save_uploaded_file($evidenceUpload);
                if (!$uploadResult['ok']) {
                    foreach ($uploadedFiles as $uploadedFile) {
                        @unlink(UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $uploadedFile['stored_name']);
                    }
                    action_fail((string)$uploadResult['error'], $back);
                }
                if ($uploadResult['file']) {
                    $uploadedFiles[] = $uploadResult['file'];
                }
            }
            $complaintId = 'CR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO complaints
                     (complaint_id, citizen_id, department_id, category, title, description, location_address,
                      latitude, longitude, is_anonymous, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Submitted")'
                );
                $insert->execute([
                    $complaintId,
                    (int)$citizen['user_id'],
                    $deptId,
                    $category,
                    $title,
                    $description,
                    $location,
                    $latitude === false ? null : $latitude,
                    $longitude === false ? null : $longitude,
                    isset($_POST['is_anonymous']) ? 1 : 0,
                ]);
                foreach ($uploadedFiles as $uploadedFile) {
                    add_evidence($complaintId, $uploadedFile);
                }
                log_activity($complaintId, (int)$citizen['user_id'], 'Report submitted', 'Report received and routed for department review.');
                $officers = $pdo->prepare(
                    "SELECT user_id FROM users WHERE role = 'department_admin' AND department_id = ?"
                );
                $officers->execute([$deptId]);
                foreach ($officers->fetchAll(PDO::FETCH_COLUMN) as $officerId) {
                    notify_user((int)$officerId, $complaintId, 'New report in your department', $category . ': ' . $title);
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($uploadedFiles as $uploadedFile) {
                    @unlink(UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $uploadedFile['stored_name']);
                }
                throw $error;
            }
            action_succeed('Your report was submitted. Save the tracking ID shown on the next screen.', page_url('track', ['id' => $complaintId]));
        }

        case 'vote': {
            $citizen = require_citizen();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $complaint = find_complaint($complaintId);
            if (!$complaint || in_array($complaint['status'], ['Resolved', 'Closed', 'Rejected'], true)) {
                action_fail('This report is not open for community votes.', page_url('track', ['id' => $complaintId]));
            }
            if ((int)$complaint['citizen_id'] === (int)$citizen['user_id']) {
                action_fail('You cannot vote on your own report.', page_url('track', ['id' => $complaintId]));
            }
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $vote = $pdo->prepare('INSERT IGNORE INTO community_votes (complaint_id, user_id) VALUES (?, ?)');
                $vote->execute([$complaintId, (int)$citizen['user_id']]);
                if ($vote->rowCount() === 0) {
                    $pdo->rollBack();
                    action_fail('You have already supported this report.', page_url('track', ['id' => $complaintId]));
                }
                $threshold = max(1, (int)setting('community_vote_threshold', '5'));
                $update = $pdo->prepare(
                    'UPDATE complaints
                     SET vote_count = vote_count + 1,
                         community_validated = IF(vote_count + 1 >= ?, 1, 0)
                     WHERE complaint_id = ?'
                );
                $update->execute([$threshold, $complaintId]);
                $state = $pdo->prepare('SELECT vote_count, community_validated FROM complaints WHERE complaint_id = ?');
                $state->execute([$complaintId]);
                $voteState = $state->fetch();
                $votes = (int)$voteState['vote_count'];
                $reached = $votes >= $threshold && (int)$complaint['community_validated'] === 0;
                log_activity(
                    $complaintId,
                    (int)$citizen['user_id'],
                    $reached ? 'Community fast-track reached' : 'Community support added',
                    $reached
                        ? 'The report reached the community support threshold and was flagged for priority review.'
                        : 'A community member supported this report.'
                );
                if ($reached) {
                    notify_user(
                        (int)$complaint['citizen_id'],
                        $complaintId,
                        'Your report reached the support threshold',
                        'The department has been notified to prioritize its verification.'
                    );
                    $officers = $pdo->prepare(
                        "SELECT user_id FROM users WHERE role = 'department_admin' AND department_id = ?"
                    );
                    $officers->execute([(int)$complaint['department_id']]);
                    foreach ($officers->fetchAll(PDO::FETCH_COLUMN) as $officerId) {
                        notify_user((int)$officerId, $complaintId, 'Community fast-track reached', $complaintId . ' reached the support threshold.');
                    }
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            action_succeed('Your support was added to this report.', page_url('track', ['id' => $complaintId]));
        }

        case 'feedback': {
            $citizen = require_citizen();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $rating = filter_var($_POST['star_rating'] ?? null, FILTER_VALIDATE_INT);
            $comments = post_string('comments', 1000);
            $complaint = find_complaint($complaintId);
            if (!$complaint
                || (int)$complaint['citizen_id'] !== (int)$citizen['user_id']
                || $complaint['status'] !== 'Resolved') {
                action_fail('Feedback is available after your report is resolved.', page_url('track', ['id' => $complaintId]));
            }
            if ($rating === false || $rating < 1 || $rating > 5) {
                action_fail('Choose a rating from 1 to 5 stars.', page_url('track', ['id' => $complaintId]));
            }
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $statement = $pdo->prepare(
                    'INSERT INTO feedback (complaint_id, user_id, star_rating, comments) VALUES (?, ?, ?, ?)'
                );
                $statement->execute([$complaintId, (int)$citizen['user_id'], $rating, $comments ?: null]);
                $update = $pdo->prepare("UPDATE complaints SET status = 'Closed' WHERE complaint_id = ? AND status = 'Resolved'");
                $update->execute([$complaintId]);
                if ($update->rowCount() !== 1) {
                    $pdo->rollBack();
                    action_fail('This report is no longer waiting for feedback.', page_url('track', ['id' => $complaintId]));
                }
                log_activity($complaintId, (int)$citizen['user_id'], 'Case closed', 'Citizen feedback was submitted and the case was closed.');
                $pdo->commit();
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($error->getCode() === '23000') {
                    action_fail('Feedback has already been submitted for this report.', page_url('track', ['id' => $complaintId]));
                }
                throw $error;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            action_succeed('Thank you. Your feedback is recorded and the case is closed.', page_url('track', ['id' => $complaintId]));
        }

        case 'read_notification': {
            $reader = is_admin() ? require_admin() : require_citizen();
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if ($notificationId !== false && $notificationId > 0) {
                $statement = db()->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
                $statement->execute([$notificationId, (int)$reader['user_id']]);
            }
            action_succeed(
                'Notification marked as read.',
                is_admin() ? admin_url('dashboard') : page_url('account')
            );
        }

        case 'admin_start_review': {
            $admin = require_admin();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $complaint = find_complaint($complaintId);
            if (!$complaint || !can_access_department($admin, (int)$complaint['department_id'])) {
                action_fail('That report is not available to your account.', admin_url('dashboard'));
            }
            if ($complaint['status'] !== 'Submitted') {
                action_fail('Only newly submitted reports can be moved into verification.', admin_url('case', ['id' => $complaintId]));
            }
            db()->prepare("UPDATE complaints SET status = 'Under Verification' WHERE complaint_id = ?")->execute([$complaintId]);
            log_activity($complaintId, (int)$admin['user_id'], 'Under verification', 'A municipal officer started reviewing this report.');
            notify_user((int)$complaint['citizen_id'], $complaintId, 'Report under verification', 'A municipal officer has started reviewing your report.');
            action_succeed('Report moved to verification.', admin_url('case', ['id' => $complaintId]));
        }

        case 'admin_verify': {
            $admin = require_admin();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $score = filter_var($_POST['validity_score'] ?? null, FILTER_VALIDATE_INT);
            $remarks = post_string('remarks', 500);
            $complaint = find_complaint($complaintId);
            if (!$complaint || !can_access_department($admin, (int)$complaint['department_id'])) {
                action_fail('That report is not available to your account.', admin_url('dashboard'));
            }
            if ($score === false || $score < 1 || $score > 5) {
                action_fail('Choose a validity score from 1 to 5.', admin_url('case', ['id' => $complaintId]));
            }
            if (!in_array($complaint['status'], ['Submitted', 'Under Verification'], true)) {
                action_fail('This report is no longer open for verification.', admin_url('case', ['id' => $complaintId]));
            }
            $threshold = max(1, min(5, (int)setting('validity_score_threshold', '3')));
            $verified = $score >= $threshold;
            if (!$verified && $remarks === '') {
                action_fail('Add a reason when rejecting a report for a low validity score.', admin_url('case', ['id' => $complaintId]));
            }
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $verification = $pdo->prepare(
                    'INSERT INTO verifications (complaint_id, officer_id, validity_score, verification_status, remarks)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $verification->execute([
                    $complaintId,
                    (int)$admin['user_id'],
                    $score,
                    $verified ? 'Verified' : 'Rejected',
                    $remarks ?: null,
                ]);
                if ($verified) {
                    $pdo->prepare(
                        "UPDATE complaints SET validity_score = ?, status = 'Work Assigned', rejection_reason = NULL
                         WHERE complaint_id = ?"
                    )->execute([$score, $complaintId]);
                    $work = $pdo->prepare(
                        "INSERT INTO work_orders (complaint_id, assigned_by, status)
                         VALUES (?, ?, 'Assigned')
                         ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), status = 'Assigned'"
                    );
                    $work->execute([$complaintId, (int)$admin['user_id']]);
                    log_activity($complaintId, (int)$admin['user_id'], 'Work assigned', 'The report passed verification and was assigned to a field team.');
                    notify_user((int)$complaint['citizen_id'], $complaintId, 'Work has been assigned', 'Your report passed verification and is now assigned to a department team.');
                } else {
                    $pdo->prepare(
                        "UPDATE complaints SET validity_score = ?, status = 'Rejected', rejection_reason = ?
                         WHERE complaint_id = ?"
                    )->execute([$score, $remarks, $complaintId]);
                    log_activity($complaintId, (int)$admin['user_id'], 'Report rejected', 'The report was rejected after verification: ' . $remarks);
                    notify_user((int)$complaint['citizen_id'], $complaintId, 'Report not approved', 'The department could not approve this report. Reason: ' . $remarks);
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            action_succeed($verified ? 'Validity recorded and work assigned.' : 'Report rejected and citizen notified.', admin_url('case', ['id' => $complaintId]));
        }

        case 'admin_update_work': {
            $admin = require_admin();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $crew = post_string('field_crew', 140);
            $expected = post_string('expected_completion', 10);
            $complaint = find_complaint($complaintId);
            if (!$complaint || !can_access_department($admin, (int)$complaint['department_id'])) {
                action_fail('That report is not available to your account.', admin_url('dashboard'));
            }
            if (!in_array($complaint['status'], ['Work Assigned', 'In Progress'], true)) {
                action_fail('A work order must be assigned before progress can be updated.', admin_url('case', ['id' => $complaintId]));
            }
            if ($expected !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected)) {
                action_fail('Enter a valid expected completion date.', admin_url('case', ['id' => $complaintId]));
            }
            $statement = db()->prepare(
                "UPDATE work_orders SET field_crew = ?, expected_completion = ?,
                        status = 'In Progress'
                 WHERE complaint_id = ?"
            );
            $statement->execute([$crew ?: null, $expected ?: null, $complaintId]);
            db()->prepare("UPDATE complaints SET status = 'In Progress' WHERE complaint_id = ?")->execute([$complaintId]);
            log_activity(
                $complaintId,
                (int)$admin['user_id'],
                'Work in progress',
                $crew !== '' ? 'Field work started with ' . $crew . '.' : 'A department field team started work.'
            );
            notify_user((int)$complaint['citizen_id'], $complaintId, 'Work is in progress', 'A department field team has started work on your report.');
            action_succeed('Work order progress updated.', admin_url('case', ['id' => $complaintId]));
        }

        case 'admin_resolve': {
            $admin = require_admin();
            $complaintId = strtoupper(post_string('complaint_id', 32));
            $notes = post_string('resolution_notes', 2000);
            $complaint = find_complaint($complaintId);
            if (!$complaint || !can_access_department($admin, (int)$complaint['department_id'])) {
                action_fail('That report is not available to your account.', admin_url('dashboard'));
            }
            if (!in_array($complaint['status'], ['Work Assigned', 'In Progress'], true)) {
                action_fail('Only assigned or active work can be marked resolved.', admin_url('case', ['id' => $complaintId]));
            }
            if (mb_strlen($notes) < 10) {
                action_fail('Add a short note explaining what the crew completed.', admin_url('case', ['id' => $complaintId]));
            }
            $uploadResult = save_uploaded_file($_FILES['proof'] ?? []);
            if (!$uploadResult['ok'] || !$uploadResult['file']) {
                action_fail($uploadResult['file'] ? 'Upload a completion photo as JPG, PNG, or WebP.' : (string)($uploadResult['error'] ?? 'Add a completion photo.'), admin_url('case', ['id' => $complaintId]));
            }
            $proof = $uploadResult['file'];
            if ($proof['mime_type'] === 'application/pdf') {
                @unlink(UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $proof['stored_name']);
                action_fail('Resolution proof must be a JPG, PNG, or WebP photo.', admin_url('case', ['id' => $complaintId]));
            }
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $resolution = $pdo->prepare(
                    'INSERT INTO resolutions
                     (complaint_id, officer_id, proof_stored_name, proof_mime_type, proof_original_name, resolution_notes)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE officer_id = VALUES(officer_id),
                       proof_stored_name = VALUES(proof_stored_name),
                       proof_mime_type = VALUES(proof_mime_type),
                       proof_original_name = VALUES(proof_original_name),
                       resolution_notes = VALUES(resolution_notes), resolved_at = CURRENT_TIMESTAMP'
                );
                $resolution->execute([
                    $complaintId,
                    (int)$admin['user_id'],
                    $proof['stored_name'],
                    $proof['mime_type'],
                    $proof['original_name'],
                    $notes,
                ]);
                $pdo->prepare("UPDATE complaints SET status = 'Resolved' WHERE complaint_id = ?")->execute([$complaintId]);
                $pdo->prepare("UPDATE work_orders SET status = 'Completed' WHERE complaint_id = ?")->execute([$complaintId]);
                log_activity($complaintId, (int)$admin['user_id'], 'Issue resolved', 'Completion proof was published to the public resolution feed.');
                notify_user((int)$complaint['citizen_id'], $complaintId, 'Your report is resolved', 'The department posted proof of work. Please review the result and share feedback.');
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                @unlink(UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $proof['stored_name']);
                throw $error;
            }
            action_succeed('Resolution proof published. The citizen can now review the work and leave feedback.', admin_url('case', ['id' => $complaintId]));
        }

        case 'admin_settings': {
            $admin = require_admin();
            if (!is_system_admin()) {
                action_fail('Only a system administrator can change platform settings.', admin_url('dashboard'));
            }
            $voteThreshold = filter_var($_POST['community_vote_threshold'] ?? null, FILTER_VALIDATE_INT);
            $validityThreshold = filter_var($_POST['validity_score_threshold'] ?? null, FILTER_VALIDATE_INT);
            if ($voteThreshold === false || $voteThreshold < 2 || $voteThreshold > 100
                || $validityThreshold === false || $validityThreshold < 1 || $validityThreshold > 5) {
                action_fail('Use 2–100 votes and a validity score from 1–5.', admin_url('settings'));
            }
            $pdo = db();
            $upsert = $pdo->prepare(
                'INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            );
            $upsert->execute(['community_vote_threshold', (string)$voteThreshold, (int)$admin['user_id']]);
            $upsert->execute(['validity_score_threshold', (string)$validityThreshold, (int)$admin['user_id']]);
            foreach (['email_notifications_enabled', 'sms_gateway_enabled'] as $toggle) {
                $upsert->execute([$toggle, isset($_POST[$toggle]) ? '1' : '0', (int)$admin['user_id']]);
            }
            action_succeed('System settings saved.', admin_url('settings'));
        }

        case 'admin_create_officer': {
            $admin = require_admin();
            if (!is_system_admin()) {
                action_fail('Only a system administrator can add officers.', admin_url('dashboard'));
            }
            $name = post_string('full_name', 120);
            $email = mb_strtolower(post_string('email', 190));
            $password = (string)($_POST['password'] ?? '');
            $accessCode = (string)($_POST['access_code'] ?? '');
            $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($password) < 10 || strlen($accessCode) < 6
                || $departmentId === false || $departmentId < 1) {
                action_fail('Enter a valid name, email, department, password (10+ characters), and access code (6+ characters).', admin_url('officers'));
            }
            $deptCheck = db()->prepare('SELECT department_id FROM departments WHERE department_id = ? AND is_active = 1');
            $deptCheck->execute([$departmentId]);
            if (!$deptCheck->fetchColumn()) {
                action_fail('Choose an active department.', admin_url('officers'));
            }
            try {
                $statement = db()->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, department_id, access_code_hash)
                     VALUES (?, ?, ?, 'department_admin', ?, ?)"
                );
                $statement->execute([
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $departmentId,
                    password_hash($accessCode, PASSWORD_DEFAULT),
                ]);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    action_fail('An account already uses that email address.', admin_url('officers'));
                }
                throw $error;
            }
            action_succeed('Department officer account created.', admin_url('officers'));
        }

        default:
            http_response_code(400);
            exit('Unknown action.');
    }
} catch (Throwable $error) {
    log_error($error);
    flash('error', 'CityRemedy could not complete that action. Check the database connection and try again.');
    $fallback = is_admin() ? admin_url('dashboard') : page_url('home');
    redirect_to(safe_return_to($fallback));
}
