<?php

namespace App\Tests\Application\Layout;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AccountMenuTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const ACCOUNT_MENU_BUTTON_TEXT = 'My account';
    private const ACCOUNT_MENU_SELECTOR = '.navigation-bar__account-menu';
    private const ACCOUNT_MENU_ITEM_SELECTOR = '.navigation-bar__account-menu-dropdown-item';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAccountMenuIsNotAvailableToAnonymousUsers(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertEmpty($crawler->selectButton(self::ACCOUNT_MENU_BUTTON_TEXT));
        self::assertSelectorNotExists(self::ACCOUNT_MENU_SELECTOR);
    }

    public function testAccountMenuIsAvailableToLoggedInUsers(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $crawler = $this->client->request('GET', '/');

        self::assertNotEmpty($crawler->selectButton(self::ACCOUNT_MENU_BUTTON_TEXT));
        self::assertSelectorExists(self::ACCOUNT_MENU_SELECTOR);
    }

    public function testAccountMenuHasLinkToAdminAreaOnlyIfUserHasAdminRole(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/');

        self::assertAnySelectorTextNotContains(self::ACCOUNT_MENU_ITEM_SELECTOR, 'Admin');

        $this->client->request('GET', '/logout');

        $admin = UserFactory::new()->admin()->create();
        $this->client->loginUser($admin->_real());

        $this->client->request('GET', '/');

        self::assertAnySelectorTextContains(self::ACCOUNT_MENU_ITEM_SELECTOR, 'Admin');
    }
}
