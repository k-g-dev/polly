<?php

namespace App\Tests\TestSupport\Trait;

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

trait ParameterAccessTrait
{
    private static array $cachedParameters = [];

    /**
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    protected static function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        if (!array_key_exists($name, self::$cachedParameters)) {
            self::cacheParameter($name);
        }

        return self::$cachedParameters[$name];
    }

    private static function cacheParameter($name): void
    {
        $wasKernelEnabled = self::$kernel !== null;

        if (!$wasKernelEnabled) {
            self::bootKernel();
        }

        self::$cachedParameters[$name] = static::getContainer()->getParameter($name);

        if (!$wasKernelEnabled) {
            self::ensureKernelShutdown();
            self::$kernel = null;
        }
    }
}
