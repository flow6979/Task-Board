<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomePageController extends AbstractController
{
    #[Route('/', name: 'app_home_page')]
    public function index(): Response
    {
        return $this->render('home_page/index.html.twig', [
            'controller_name' => 'HomePageController',
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgotPass_page')]
    public function ForgotPass(): Response
    {
        return $this->render('home_page/forgotPass.html.twig', [
            'controller_name' => 'HomePageController',
        ]);
    }
}
