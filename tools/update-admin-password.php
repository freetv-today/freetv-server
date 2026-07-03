<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=freetv;charset=utf8mb4', 'root', 'guslives', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$hash = '$2y$12$yC4l6qMrscz3JZ3KxpeJjeJN5oxViSULdjqSQmbe0LlbwRFBVXGRS';
$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE username = :username');
$stmt->execute([':hash' => $hash, ':username' => 'admin']);
echo $stmt->rowCount(), PHP_EOL;
