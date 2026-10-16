<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ShowGroup.php';

use FreeTV\Admin\ShowGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShowGroupTest extends TestCase
{
    #[DataProvider('validGroupProvider')]
    public function testNormalizesStringGroups(string $input, ?string $expected): void
    {
        self::assertSame($expected, ShowGroup::normalize($input));
    }

    public static function validGroupProvider(): array
    {
        return [
            'empty' => ['', null],
            'spaces' => ['   ', null],
            'trimmed' => ['  Foo  ', 'Foo'],
            'ordinary' => ['Saturday Morning', 'Saturday Morning'],
            'unicode' => ['  Télévision 日本  ', 'Télévision 日本'],
        ];
    }

    public function testOmittedGroupNormalizesToNull(): void
    {
        self::assertNull(ShowGroup::fromShow([]));
    }

    public function testFromShowNormalizesSuppliedString(): void
    {
        self::assertSame('Drama', ShowGroup::fromShow(['group' => '  Drama ']));
    }

    #[DataProvider('invalidGroupProvider')]
    public function testRejectsNonStringGroups(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Group must be a string');
        ShowGroup::normalize($value);
    }

    public static function invalidGroupProvider(): array
    {
        return [
            'explicit null' => [null],
            'integer' => [42],
            'float' => [1.5],
            'array' => [[]],
            'true' => [true],
            'false' => [false],
            'object' => [new stdClass()],
        ];
    }

    public function testExplicitNullInShowIsRejectedUnlikeOmittedGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Group must be a string');
        ShowGroup::fromShow(['group' => null]);
    }
}
