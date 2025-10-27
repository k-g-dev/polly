<?php

namespace App\Controller\Auth;

use App\Form\Handler\RegistrationFormHandler;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public const ROUTE_REGISTER = 'app_register';

    #[Route('/register', name: 'app_register', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function register(
        RegistrationFormHandler $formHandler,
        Request $request,
    ): Response {
        $form = $this->createForm(RegistrationFormType::class);

        $response = $formHandler->handle($form, $request);

        return $response ?? $this->render('auth/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
