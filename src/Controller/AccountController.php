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

#[Route('/account', name: 'app_account_')]
final class AccountController extends AbstractController
{
    public const ROUTE_INDEX = 'app_account_index';
    public const ROUTE_PASSWORD_CHANGE = 'app_account_password_change';
    public const ROUTE_TERMS_OF_SERVICE_ACCEPTANCE = 'app_account_terms_of_service_acceptance';

    #[Route(name: 'index', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('account/index.html.twig');
    }

    #[Route(
        '/terms-of-service/acceptance',
        name: 'terms_of_service_acceptance',
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

        return $this->render('account/terms_of_service_acceptance.html.twig', [
            'agreeToTermsForm' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/password/change', name: 'password_change', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function passwordChange(Request $request, UserPasswordManager $userPasswordManager): Response
    {
        $form = $this->createForm(PasswordChangeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userPasswordManager->changePassword($this->getUser(), $form->getData());
            $this->addFlash(FlashMessageType::Success->value, 'The password has been changed.');

            return $this->redirectToRoute(self::ROUTE_INDEX);
        }

        return $this->render('account/password_change.html.twig', [
            'passwordChangeForm' => $form,
        ]);
    }
}
