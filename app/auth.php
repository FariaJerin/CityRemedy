<?php
declare(strict_types=1);

function current_user(): ?array
{
    $user = $_SESSION['cityremedy_user'] ?? null;
    return is_array($user) ? $user : null;
}

function is_citizen(): bool
{
    return (current_user()['role'] ?? '') === 'citizen';
}

function is_admin(): bool
{
    return in_array(current_user()['role'] ?? '', ['department_admin', 'system_admin'], true);
}

function is_system_admin(): bool
{
    return (current_user()['role'] ?? '') === 'system_admin';
}

function require_citizen(): array
{
    if (!is_citizen()) {
        flash('error', 'Sign in with a citizen account to continue.');
        redirect_to(page_url('login'));
    }
    return current_user();
}

function require_admin(): array
{
    if (!is_admin()) {
        flash('error', 'Sign in with an authorized officer account to continue.');
        redirect_to(admin_url('login'));
    }
    return current_user();
}

function can_access_department(array $user, int $departmentId): bool
{
    return $user['role'] === 'system_admin'
        || (int)($user['department_id'] ?? 0) === $departmentId;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['cityremedy_user'] = [
        'user_id' => (int)$user['user_id'],
        'full_name' => (string)$user['full_name'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
        'department_id' => $user['department_id'] === null ? null : (int)$user['department_id'],
        'department_name' => $user['department_name'] ?? null,
    ];
}

function logout_user(): void
{
    unset($_SESSION['cityremedy_user']);
    session_regenerate_id(true);
}
