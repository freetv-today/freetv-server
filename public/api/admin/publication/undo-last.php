<?php

require_once __DIR__ . '/../Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

function respondToPublicationUndo(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondToPublicationUndo(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PublicationUndoService.php';

use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;

try {
    $result = (new PublicationUndoService())->undo();
    respondToPublicationUndo(200, [
        'success' => true,
        'message' => 'Last publication restored successfully',
        'undo' => $result,
    ]);
} catch (PublicationException $exception) {
    error_log('Publication Undo Error: ' . $exception->getMessage());
    respondToPublicationUndo($exception->getHttpStatus(), [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('Publication Undo Error: ' . $exception->getMessage());
    respondToPublicationUndo(500, [
        'success' => false,
        'message' => 'Could not undo the last publication',
    ]);
}
