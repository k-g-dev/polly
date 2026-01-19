<?php

namespace App\Controller;

use App\Const\Common;
use App\Enum\FlashMessageType;
use App\Form\AgreeToTermsFormType;
use App\Form\PasswordChangeFormType;
use App\Manager\UserManager;
use App\Manager\UserPasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/account', name: self::ROUTE_GROUP_PREFIX)]
final class AccountController extends AbstractController
{
    public const ROUTE_INDEX = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_INDEX;
    public const ROUTE_PASSWORD_CHANGE = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_PASSWORD_CHANGE;
    public const ROUTE_TERMS_OF_SERVICE_ACCEPTANCE
        = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_TERMS_OF_SERVICE_ACCEPTANCE;

    private const INTERNAL_ROUTE_INDEX = 'index';
    private const INTERNAL_ROUTE_PASSWORD_CHANGE = 'password_change';
    private const INTERNAL_ROUTE_TERMS_OF_SERVICE_ACCEPTANCE = 'terms_of_service_acceptance';
    private const ROUTE_GROUP_PREFIX = 'app_account_';

    #[Route(name: self::INTERNAL_ROUTE_INDEX, methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('site/account/index.html.twig');
    }

    #[Route(
        '/terms-of-service/acceptance',
        name: self::INTERNAL_ROUTE_TERMS_OF_SERVICE_ACCEPTANCE,
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function termsOfServiceAcceptance(Request $request, UserManager $userManager): Response
    {
        $user = $this->getUser();

        if (!$user || $user->hasAgreedToTerms()) {
            return $this->redirectToRoute(self::ROUTE_INDEX);
        }

        $form = $this->createForm(AgreeToTermsFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $agreeTerms = $form->get('agreeTerms')->getData();

            if ($agreeTerms) {
                $userManager->agreeToTerms($user);
            }

            $session = $request->getSession();

            $targerUrl = $session->get(
                Common::AGREE_TO_TERMS_TARGET_URL_AFTER,
                $this->generateUrl(self::ROUTE_INDEX),
            );

            $session->remove(Common::AGREE_TO_TERMS_TARGET_URL_AFTER);

            return $this->redirect($targerUrl);
        }

        return $this->render('site/account/terms_of_service_acceptance.html.twig', [
            'agreeToTermsForm' => $form,
        ]);
    }

    #[Route(
        '/password/change',
        name: self::INTERNAL_ROUTE_PASSWORD_CHANGE,
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function passwordChange(
        Request $request,
        UserPasswordManager $userPasswordManager,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(PasswordChangeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userPasswordManager->changePassword($this->getUser(), $form->getData()->plainPassword);

            $this->addFlash(
                FlashMessageType::Success->value,
                $translator->trans('account.password_change.flash_message.password_change_success', domain: 'sites'),
            );

            return $this->redirectToRoute(self::ROUTE_INDEX);
        }

        return $this->render('site/account/password_change.html.twig', [
            'passwordChangeForm' => $form,
        ]);
    }
}
