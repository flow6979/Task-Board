<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{

    #[Route('/home', name: 'homepage')]
    public function index(){
        return $this->render('home_page/index.html.twig', [
            'text' => "It's home page",
        ]);
    }
}