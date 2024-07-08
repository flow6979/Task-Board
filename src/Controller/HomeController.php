<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class HomeController extends AbstractController
{
    #[Route('api/home', name: 'app_home')]
    public function home(Request $request,JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $jwtManager->parse($data['token']);
        return new JsonResponse(['roles' => $user['roles'],'email' => $user['username']]);
        // return new JsonResponse(["user" => json_encode($user)]);
    }
}