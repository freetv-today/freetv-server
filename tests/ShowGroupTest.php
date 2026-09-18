<?php

require_once __DIR__ . '/../public/api/admin/ShowGroup.php';

use FreeTV\Admin\ShowGroup;

function assertSameGroup($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

assertSameGroup(null, ShowGroup::fromShow([]), 'Omitted group should normalize to null');
assertSameGroup(null, ShowGroup::normalize(''), 'Empty group should normalize to null');
assertSameGroup(null, ShowGroup::normalize('   '), 'Whitespace group should normalize to null');
assertSameGroup('Foo', ShowGroup::normalize('  Foo  '), 'Group should be trimmed');

try {
    ShowGroup::normalize([]);
    throw new RuntimeException('Non-string group should be rejected');
} catch (InvalidArgumentException $e) {
    assertSameGroup('Group must be a string', $e->getMessage(), 'Unexpected validation message');
}

echo "ShowGroup tests passed\n";
