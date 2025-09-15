<?php

namespace App\Tests\Unit\Twig\Extension;

use App\Twig\Extension\AppExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppExtensionTest extends TestCase
{
    private AppExtension $appExtension;

    protected function setUp(): void
    {
        $this->appExtension = new AppExtension();
    }

    #[DataProvider('alertClassFunctionCasesProvider')]
    public function testAlertClassFunction(string $expected, array $params = []): void
    {
        $result = $this->appExtension->alertClass(...$params);

        $this->assertSame($expected, $result);
    }

    public static function alertClassFunctionCasesProvider(): \Generator
    {
        yield 'With valid type' => [
            'expected' => 'alert alert-info',
            'params' => [
                'type' => 'info',
            ],
        ];

        yield 'With another valid type' => [
            'expected' => 'alert alert-success',
            'params' => [
                'type' => 'success',
            ],
        ];

        yield 'With invalid type' => [
            'expected' => 'alert alert-primary',
            'params' => [
                'type' => 'invalid',
            ],
        ];

        yield 'With invalid type and changed default type' => [
            'expected' => 'alert alert-dark',
            'params' => [
                'type' => 'invalid',
                'defaultType' => 'dark',
            ],
        ];
    }
}
