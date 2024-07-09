<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class HomeController extends AbstractController
{
    #[Route('api/checkToken', name: 'check_token', methods: ['POST'])]
    public function home(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token'])) {
            return new JsonResponse(["tokenMsg" => "No Token Found"], 200);
        }

        try {
            $userData = $jwtManager->parse($data['token']);
            if (!$userData || !isset($userData['username'])) {
                return new JsonResponse(["InvalidToken" => "Invalid Token"]);
            }
        } catch (\Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException $e) {

            return new JsonResponse(["ExpiredToken" => "Invalid or Expired Token"]);
        } catch (\Exception $e) {
            
            return new JsonResponse(["InvalidToken" => "An error occurred while processing the token"]);
        }

        $user = $userRepository->findOneBy(['email' => $userData['username']]);

        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }

        $authUser = [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles(),
            'phone_number' => $user->getPhoneNumber(),
        ];

        return new JsonResponse(['user' => $authUser], Response::HTTP_OK);
    }
}
