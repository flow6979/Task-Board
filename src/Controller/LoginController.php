<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;


class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login',methods:['POST'])]
    public function login(AuthenticationUtils $authenticationUtils): JsonResponse
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $errorMessage = $error ? $error->getMessageKey() : null;
        return new JsonResponse([
            'last_username' => $lastUsername,
            'error' => $errorMessage,
        ]);
    }
    /**
     * @Route("/user_logout", name="user_logout")
     */
    #[Route('/logout', name: 'app_logout')]
    public function userLogout(Request $request)
    {
        $this->addFlash('success', 'You have been logged out successfully. 2');

        return $this->redirectToRoute('customer_login');
    }

}