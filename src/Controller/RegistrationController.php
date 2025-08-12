<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FlashMessageType;
use App\Form\RegistrationFormType;
use App\Manager\UserManager;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\ConfirmationEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
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
            $verificationLifetime = $confirmationEmailSender->getVerificationLifetime();

            $this->addFlash(
                FlashMessageType::Info->value,
                "Please verify your email address. The verification link is valid for {$verificationLifetime}.",
            );

            return $this->redirectToRoute('app_homepage');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: [Request::METHOD_GET])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EmailVerifier $emailVerifier,
    ): Response {
        $id = $request->query->get('id');

        $user = ($id !== null) ? $userRepository->find($id) : null;

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(FlashMessageType::Danger->value, $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash(FlashMessageType::Success->value, 'Your email address has been verified.');

        return $this->redirectToRoute('app_homepage');
    }
}
