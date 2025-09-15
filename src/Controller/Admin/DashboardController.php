<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public const ROUTE_INDEX = 'app_admin_dashboard';

    #[Route('/admin', name: 'app_admin_dashboard', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig');
    }
}
