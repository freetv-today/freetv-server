<?php

require_once __DIR__ . '/Session.php';

\FreeTV\Admin\destroyAdminSession();
header('Content-Type: application/json');
echo json_encode(['success' => true]);
