<?php

namespace App\Tests\TestSupport\Trait;

trait RateLimiterResetTrait
{
    protected function resetRateLimiter(): void
    {
        self::getContainer()
            ->get('cache.global_clearer')
            ->clearPool('cache.rate_limiter');
    }
}
