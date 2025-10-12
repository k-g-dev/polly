<?php

namespace App\Controller\Auth;

use App\Enum\FlashMessageType;
use App\Form\RegistrationFormType;
use App\Manager\UserManager;
use App\Service\EmailSender\ConfirmationEmailSender;
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
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userManager->create($form->getData());

            $confirmationEmailSender->send($user);
            $this->addFlash(FlashMessageType::Info->value, $confirmationEmailSender->getInstruction());

            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        return $this->render('auth/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
