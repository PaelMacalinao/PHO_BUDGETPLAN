<?php
/**
 * PHO Budgeting System — Shared Configuration
 */

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'pho_budgeting');
define('DB_USER',    'root');
define('DB_PASS',    'root');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', '2026 Conso Proposal V3');
define('APP_ORG',  'Provincial Health Office');

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function peso(float $v): string
{
    return '₱ ' . number_format($v, 2);
}

function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function getUserRole(): string
{
    initSession();
    return $_SESSION['user_role'] ?? 'admin';
}

function isAdmin(): bool
{
    return getUserRole() === 'admin';
}

function setUserRole(string $role): void
{
    initSession();
    $_SESSION['user_role'] = in_array($role, ['admin', 'staff'], true) ? $role : 'staff';
}
