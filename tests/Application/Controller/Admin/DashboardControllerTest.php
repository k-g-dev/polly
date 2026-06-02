<?php

namespace App\Tests\Application\Controller\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Auth\SecurityController;
use App\Controller\MainController;
use App\Factory\UserFactory;
use App\Tests\TestSupport\Trait\LocaleManagementTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DashboardControllerTest extends WebTestCase
{
    use Factories;
    use LocaleManagementTrait;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAdminHasAccessToAdminDashboard(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $this->client->loginUser($admin->_real());

        $this->client->request(Request::METHOD_GET, '/admin');
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('enabledLocalesProvider')]
    public function testUserDoesNotHaveAccessToAdminDashboard(string $locale): void
    {
        $translator = static::getContainer()->get(TranslatorInterface::class);

        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request(
            Request::METHOD_GET,
            $this->getLocalizedRouteUrl(DashboardController::ROUTE_INDEX, $locale),
        );
        self::assertResponseRedirects();
        $this->client->followRedirect();

        $this->assertSame($locale, $this->client->getRequest()->getLocale());

        self::assertRouteSame(MainController::ROUTE_HOMEPAGE);
        self::assertSelectorTextSame('.alert-danger', $translator->trans(
            'access_control.access_denied.default',
            domain: 'security',
            locale: $locale,
        ));
    }

    #[DataProvider('enabledLocalesProvider')]
    public function testAnonymousUserDoesNotHaveAccessToAdminDashboard(string $locale): void
    {
        $this->client->request(
            Request::METHOD_GET,
            $this->getLocalizedRouteUrl(DashboardController::ROUTE_INDEX, $locale),
        );
        self::assertResponseRedirects($this->getLocalizedRouteUrl(SecurityController::ROUTE_LOGIN, $locale), 302);
    }
}
