<?php

header('Content-Type: application/json');

session_start();

$sessionUser = $_SESSION['admin'] ?? null;
if (
    is_array($sessionUser)
    && isset($sessionUser['id'], $sessionUser['username'], $sessionUser['role'])
    && is_int($sessionUser['id'])
    && $sessionUser['id'] > 0
    && is_string($sessionUser['username'])
    && is_string($sessionUser['role'])
) {
    echo json_encode([
        'loggedIn' => true,
        'user' => [
            'id' => $sessionUser['id'],
            'username' => $sessionUser['username'],
            'role' => $sessionUser['role'],
        ],
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
