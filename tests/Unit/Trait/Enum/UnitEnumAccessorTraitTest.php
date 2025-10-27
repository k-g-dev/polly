<?php

namespace App\Tests\Unit\Trait\Enum;

use App\Tests\TestSupport\Fixtures\Enum\TestEnumUnit;
use PHPUnit\Framework\TestCase;

final class UnitEnumAccessorTraitTest extends TestCase
{
    public function testNames(): void
    {
        self::assertSame(['First', 'Second', 'Third'], TestEnumUnit::names());
    }
}
