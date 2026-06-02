<?php

namespace App\Tests\Application\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MainControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomepage(): void
    {
        $this->client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
    }

    public function testTermsOfService(): void
    {
        $translator = static::getContainer()->get(TranslatorInterface::class);

        $this->client->request(Request::METHOD_GET, '/terms-of-service');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($translator->trans('main.terms_of_service.title', domain: 'sites'));
        self::assertSelectorTextSame('h1', $translator->trans('main.terms_of_service.heading', domain: 'sites'));
    }
}
