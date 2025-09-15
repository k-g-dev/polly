<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    public const ROUTE_HOMEPAGE = 'app_homepage';
    public const ROUTE_TERMS_OF_SERVICE = 'app_terms_of_service';

    #[Route('/', name: 'app_homepage', methods: [Request::METHOD_GET])]
    public function homepage(): Response
    {
        return $this->render('main/homepage.html.twig');
    }

    #[Route('/terms-of-service', name: 'app_terms_of_service', methods: [Request::METHOD_GET])]
    public function termsOfService(): Response
    {
        return $this->render('main/terms_of_service.html.twig');
    }
}
