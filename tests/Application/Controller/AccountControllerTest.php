<?php

namespace App\Tests\Application\Controller;

use App\Const\Common;
use App\Controller\AccountController;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\force;

final class AccountControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testUserHasAccessToAccountSection(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
    }

    public function testAnonymousUserDoesNotHaveAccessToAccountSection(): void
    {
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login', 302);
    }

    public function testTermsOfServiceAcceptance(): void
    {
        $user = UserFactory::createOne(['agreedTermsAt' => force(null)]);
        $this->client->loginUser($user->_real());

        self::assertFalse($user->hasAgreedToTerms());

        $this->client->request('GET', '/account');

        $initialTargetUrl = $this->client->getRequest()->getUri();

        /** @var Session $sessionBeforeAcceptTerms */
        $sessionBeforeAcceptTerms = $this->client->getRequest()->getSession();
        self::assertSame($initialTargetUrl, $sessionBeforeAcceptTerms->get(Common::AGREE_TO_TERMS_TARGET_URL_AFTER));

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(AccountController::ROUTE_TERMS_OF_SERVICE_ACCEPTANCE);

        $this->client->submitForm('Submit', [
            'agree_to_terms_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects($initialTargetUrl);
        $this->client->followRedirect();

        self::assertTrue($user->hasAgreedToTerms());

        /** @var Session $sessionAfterAcceptTerms */
        $sessionAfterAcceptTerms = $this->client->getRequest()->getSession();
        self::assertFalse($sessionAfterAcceptTerms->has(Common::AGREE_TO_TERMS_TARGET_URL_AFTER));
    }
}
