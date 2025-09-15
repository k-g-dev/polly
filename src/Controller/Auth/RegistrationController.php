<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Enum\FlashMessageType;
use App\Form\RegistrationFormType;
use App\Manager\UserManager;
use App\Service\ConfirmationEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public const ROUTE_REGISTER = 'app_register';

    #[Route('/register', name: 'app_register', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function register(
        Request $request,
        ConfirmationEmailSender $confirmationEmailSender,
        UserManager $userManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $agreeTerms = $form->get('agreeTerms')->getData();

            $userManager->create($user, $plainPassword, $agreeTerms);

            $confirmationEmailSender->send($user);
            $this->addFlash(FlashMessageType::Info->value, $confirmationEmailSender->getInstruction());

            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
