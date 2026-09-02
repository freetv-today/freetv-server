<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/SqlPackageExecutor.php';

use FreeTV\Admin\SqlPackageExecutor;

$executor = new SqlPackageExecutor();
$statements = $executor->statements(<<<'SQL'
-- leading comment
CREATE TABLE `example` (`value` VARCHAR(100));
INSERT INTO `example` VALUES ('semi;colon'), ("quoted;value"), ('it''s safe');
/* block ; comment */
# hash ; comment
INSERT INTO `example` VALUES ('escaped\'quote');
SQL);

if (count($statements) !== 3) {
    throw new RuntimeException('SQL parser split semicolons inside strings or comments');
}
if (!str_contains($statements[1], "'semi;colon'")) {
    throw new RuntimeException('SQL parser changed quoted content');
}

$canonical = file_get_contents(__DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql');
if ($canonical === false || count($executor->statements($canonical)) !== 7) {
    throw new RuntimeException('Canonical tables-only package was not parsed into seven statements');
}

fwrite(STDOUT, "SqlPackageExecutorTest passed\n");
