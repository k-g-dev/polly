<?php

namespace App\Tests\Application\Layout;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AccountMenuTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const ACCOUNT_MENU_ITEM_SELECTOR = '.navigation-bar__account-menu-dropdown-item';
    private const ACCOUNT_MENU_SELECTOR = '.navigation-bar__account-menu';

    private KernelBrowser $client;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->translator = static::getContainer()->get(TranslatorInterface::class);
    }

    public function testAccountMenuIsNotAvailableToAnonymousUsers(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertEmpty($crawler->selectButton($this->translator->trans('menu.account.base.my_account')));
        self::assertSelectorNotExists(self::ACCOUNT_MENU_SELECTOR);
    }

    public function testAccountMenuIsAvailableToLoggedInUsers(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $crawler = $this->client->request('GET', '/');

        self::assertNotEmpty($crawler->selectButton($this->translator->trans('menu.account.base.my_account')));
        self::assertSelectorExists(self::ACCOUNT_MENU_SELECTOR);
    }

    public function testAccountMenuHasLinkToAdminAreaOnlyIfUserHasAdminRole(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/');

        self::assertAnySelectorTextNotContains(
            self::ACCOUNT_MENU_ITEM_SELECTOR,
            $this->translator->trans('menu.account.base.admin_area'),
        );

        $this->client->request('GET', '/logout');

        $admin = UserFactory::new()->admin()->create();
        $this->client->loginUser($admin->_real());

        $this->client->request('GET', '/');

        self::assertAnySelectorTextSame(
            self::ACCOUNT_MENU_ITEM_SELECTOR,
            $this->translator->trans('menu.account.base.admin_area'),
        );
    }
}
