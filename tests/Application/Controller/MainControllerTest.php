<?php

namespace App\Tests\Application\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MainControllerTest extends WebTestCase
{
    public function testHomepage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testTermsOfService(): void
    {
        $client = static::createClient();
        $client->request('GET', '/terms-of-service');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Terms of Service');
        self::assertPageTitleContains('Terms of Service');
    }
}
