<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminPageController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_page',methods:['GET'])]
    public function index(): Response
    {
        return $this->render('admin_page/index.html.twig', [
            'controller_name' => 'AdminPageController',
        ]);
    }
    #[Route('/admin/teams/{team_id}', name: 'admin_team_page',methods:['GET'])]
    public function TeamPage($team_id): Response
    {
        return $this->render('admin_page/team.html.twig', [
            'controller_name' => 'AdminPageController',
            'team_id' => $team_id
        ]);
    }
}
