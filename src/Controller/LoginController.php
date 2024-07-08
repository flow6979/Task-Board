<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
// use AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class LoginController extends AbstractController
{
    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function loginCheck(Request $request, UserPasswordHasherInterface $passwordHasher, JWTTokenManagerInterface $jwtManager, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse(['token' => $token, 'redirect' => in_array('ROLE_ADMIN', $user->getRoles()) ? '/api/admin' : '/api/home']);
    }

    #[Route("/login", name : "app_login")]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/api/admin', name: 'api_admin')]
    public function adminPage(UserInterface $user): JsonResponse
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['message' => 'Access denied'], 403);
        }

        return new JsonResponse(['message' => 'Welcome to the admin page']);
    }

    #[Route('/api/home', name: 'api_home')]
    public function homePage(): JsonResponse
    {
        return new JsonResponse(['message' => 'Welcome to the home page']);
    }
}