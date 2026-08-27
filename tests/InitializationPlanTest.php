<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/InitializationPlan.php';

use FreeTV\Admin\InitializationPlan;

function initializationPlanAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

initializationPlanAssertSame(
    InitializationPlan::ALREADY_INITIALIZED,
    InitializationPlan::forState(true, false, false),
    'Existing users must block initialization'
);
initializationPlanAssertSame(
    InitializationPlan::CREATE_ADMIN_AND_STARTER,
    InitializationPlan::forState(false, false, false),
    'Empty installation must create starter content'
);
initializationPlanAssertSame(
    InitializationPlan::CREATE_ADMIN_ONLY,
    InitializationPlan::forState(false, true, false),
    'Preloaded empty playlist must be preserved'
);
initializationPlanAssertSame(
    InitializationPlan::CREATE_ADMIN_ONLY,
    InitializationPlan::forState(false, true, true),
    'Preloaded SQL package content must be preserved'
);

try {
    InitializationPlan::forState(false, false, true);
    throw new RuntimeException('Expected inconsistent content state failure');
} catch (RuntimeException $exception) {
    initializationPlanAssertSame(
        'Playlist shows exist without a parent playlist',
        $exception->getMessage(),
        'Unexpected inconsistent-state error'
    );
}

fwrite(STDOUT, "InitializationPlanTest passed\n");
