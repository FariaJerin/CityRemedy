<?php
declare(strict_types=1);

function category_department_slug(string $category): string
{
    return match ($category) {
        'Road damage' => 'roads',
        'Streetlight failure' => 'lighting',
        'Water leakage' => 'water',
        'Garbage accumulation' => 'waste',
        'Drainage blockage' => 'drainage',
        default => 'general',
    };
}

function categories(): array
{
    return [
        'Road damage',
        'Streetlight failure',
        'Water leakage',
        'Garbage accumulation',
        'Drainage blockage',
        'Other',
    ];
}

function department_id_for(string $slug): ?int
{
    $statement = db()->prepare('SELECT department_id FROM departments WHERE slug = ? AND is_active = 1');
    $statement->execute([$slug]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (int)$value;
}

function find_complaint(string $complaintId): ?array
{
    $statement = db()->prepare(
        'SELECT c.*, d.department_name,
                CASE WHEN c.is_anonymous = 1 THEN "Community member" ELSE u.full_name END AS reporter_name
         FROM complaints c
         JOIN departments d ON d.department_id = c.department_id
         JOIN users u ON u.user_id = c.citizen_id
         WHERE c.complaint_id = ?'
    );
    $statement->execute([$complaintId]);
    $complaint = $statement->fetch();
    return $complaint ?: null;
}

function similar_complaints(string $category, string $location, ?string $excludeId = null, int $limit = 5): array
{
    if (mb_strlen($location) < 4) {
        return [];
    }
    $sql = "SELECT complaint_id, category, title, location_address, status, vote_count, submitted_at
            FROM complaints
            WHERE category = ?
              AND status NOT IN ('Resolved', 'Closed', 'Rejected')
              AND location_address LIKE ?";
    $params = [$category, '%' . $location . '%'];
    if ($excludeId !== null) {
        $sql .= ' AND complaint_id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY vote_count DESC, submitted_at DESC LIMIT ' . max(1, min($limit, 10));
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function public_resolutions(int $limit = 8): array
{
    $statement = db()->prepare(
        "SELECT c.complaint_id, c.category, c.title, c.location_address, c.status,
                c.submitted_at, r.resolution_notes, r.proof_stored_name,
                r.proof_mime_type, r.resolved_at, d.department_name
         FROM complaints c
         JOIN resolutions r ON r.complaint_id = c.complaint_id
         JOIN departments d ON d.department_id = c.department_id
         WHERE c.status IN ('Resolved', 'Closed')
         ORDER BY r.resolved_at DESC LIMIT ?"
    );
    $statement->bindValue(1, $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function complaint_timeline(string $complaintId): array
{
    $statement = db()->prepare(
        'SELECT activity_type, log_description, created_at
         FROM activity_logs WHERE complaint_id = ? ORDER BY created_at ASC'
    );
    $statement->execute([$complaintId]);
    return $statement->fetchAll();
}

function complaint_evidence(string $complaintId): array
{
    $statement = db()->prepare(
        'SELECT evidence_id, original_name, mime_type, file_size
         FROM evidence_files WHERE complaint_id = ? ORDER BY uploaded_at ASC'
    );
    $statement->execute([$complaintId]);
    return $statement->fetchAll();
}

function feedback_for_user(string $complaintId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT star_rating, comments FROM feedback WHERE complaint_id = ? AND user_id = ?'
    );
    $statement->execute([$complaintId, $userId]);
    $feedback = $statement->fetch();
    return $feedback ?: null;
}

function public_stats(): array
{
    $row = db()->query(
        "SELECT COUNT(*) AS total,
                SUM(status IN ('Submitted', 'Under Verification')) AS reviewing,
                SUM(status IN ('Work Assigned', 'In Progress')) AS active,
                SUM(status IN ('Resolved', 'Closed')) AS resolved
         FROM complaints"
    )->fetch();
    return [
        'total' => (int)($row['total'] ?? 0),
        'reviewing' => (int)($row['reviewing'] ?? 0),
        'active' => (int)($row['active'] ?? 0),
        'resolved' => (int)($row['resolved'] ?? 0),
    ];
}

function department_complaint_rows(array $user, string $filter = 'open'): array
{
    $where = [];
    $params = [];
    if ($user['role'] !== 'system_admin') {
        $where[] = 'c.department_id = ?';
        $params[] = (int)$user['department_id'];
    }
    if ($filter === 'open') {
        $where[] = "c.status NOT IN ('Resolved', 'Closed', 'Rejected')";
    } elseif ($filter === 'resolved') {
        $where[] = "c.status IN ('Resolved', 'Closed')";
    } elseif ($filter === 'rejected') {
        $where[] = "c.status = 'Rejected'";
    }
    $sql = "SELECT c.complaint_id, c.category, c.title, c.location_address,
                   c.status, c.vote_count, c.community_validated, c.validity_score,
                   c.submitted_at, d.department_name,
                   CASE WHEN c.is_anonymous = 1 THEN 'Community member' ELSE u.full_name END AS reporter_name
            FROM complaints c
            JOIN departments d ON d.department_id = c.department_id
            JOIN users u ON u.user_id = c.citizen_id";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.community_validated DESC, c.submitted_at ASC LIMIT 100';
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function admin_metrics(array $user): array
{
    $where = '';
    $params = [];
    if ($user['role'] !== 'system_admin') {
        $where = ' WHERE department_id = ?';
        $params[] = (int)$user['department_id'];
    }
    $statement = db()->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status IN ('Submitted', 'Under Verification')) AS awaiting_review,
                SUM(status IN ('Work Assigned', 'In Progress')) AS active_work,
                SUM(status IN ('Resolved', 'Closed')) AS resolved,
                SUM(community_validated = 1 AND status NOT IN ('Resolved','Closed','Rejected')) AS fast_track
         FROM complaints{$where}"
    );
    $statement->execute($params);
    $row = $statement->fetch() ?: [];
    return array_map(static fn($value): int => (int)$value, $row);
}
