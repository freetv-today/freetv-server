<?php

namespace FreeTV\Admin;

function authorizationResponse(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requireRole(string $minimumRole = 'viewer'): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $roleRank = [
        'viewer' => 1,
        'editor' => 2,
        'admin' => 3,
    ];

    $sessionUser = $_SESSION['admin'] ?? null;
    if (
        !is_array($sessionUser)
        || !isset($sessionUser['id'], $sessionUser['username'], $sessionUser['role'])
        || !is_int($sessionUser['id'])
        || $sessionUser['id'] < 1
        || !is_string($sessionUser['username'])
        || $sessionUser['username'] === ''
        || !is_string($sessionUser['role'])
        || !isset($roleRank[$sessionUser['role']])
    ) {
        authorizationResponse(401, 'Unauthorized');
    }

    if (!isset($roleRank[$minimumRole])) {
        throw new \InvalidArgumentException('Unsupported minimum role');
    }

    if ($roleRank[$sessionUser['role']] < $roleRank[$minimumRole]) {
        authorizationResponse(403, 'Forbidden');
    }

    return $sessionUser;
}
