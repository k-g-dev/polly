<?php

namespace App\Tests\Application\Controller\Admin;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DashboardControllerTest extends WebTestCase
{
    use Factories;
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

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testUserDoesNotHaveAccessToAdminDashboard(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousUserDoesNotHaveAccessToAdminDashboard(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login', 302);
    }
}
