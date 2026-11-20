<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

final class FrameworkPatchTest extends TestCase
{
    public function testExceptionTemplateContainsBackportedXssFix(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2) . '/vendor/topthink/framework/src/tpl/think_exception.tpl'
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            "sprintf('\\'%s\\' => %s', htmlentities(\$key), \$value)",
            $template
        );
        self::assertStringNotContainsString(
            '"\'{$key}\' => {$value}"',
            $template
        );
    }
}
