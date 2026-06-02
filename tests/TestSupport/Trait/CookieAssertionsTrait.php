<?php

namespace App\Tests\TestSupport\Trait;

use Symfony\Component\BrowserKit\AbstractBrowser;

trait CookieAssertionsTrait
{
    protected static function assertCookieDeleted(string $name, AbstractBrowser $client): void
    {
        $isCookieDeleted = false;

        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                $isCookieDeleted = ($cookie->getExpiresTime() === 1);
                break;
            }
        }

        self::assertTrue($isCookieDeleted, "Cookie {$name} should be deleted.");
    }
}
