<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public const ROUTE_INDEX = 'app_admin_dashboard';

    #[Route('/admin', name: self::ROUTE_INDEX, methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('site/admin/dashboard/index.html.twig');
    }
}
