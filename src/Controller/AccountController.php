<?php

namespace App\Controller;

use App\Const\Common;
use App\Form\AgreeToTermsFormType;
use App\Manager\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account', name: 'app_account_')]
final class AccountController extends AbstractController
{
    public const ROUTE_INDEX = 'app_account_index';
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
}
