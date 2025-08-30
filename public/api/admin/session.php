<?php

session_start();
header('Content-Type: application/json');
if (isset($_SESSION['admin'])) {
    echo json_encode(['loggedIn' => true, 'user' => $_SESSION['admin']]);
} else {
    echo json_encode(['loggedIn' => false]);
}
