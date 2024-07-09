<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;


class UserController extends AbstractController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/user', name: 'get_all_users', methods: ['GET'])]
    public function getAllUsers(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();
        $usersArray = [];

        foreach ($users as $user) {
            $usersArray[] = [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'phoneNumber' => $user->getPhoneNumber(),
            ];
        }

        return new JsonResponse($usersArray, Response::HTTP_OK);
    }

    #[Route('/getUser', name: 'get_user', methods: ['POST'])]
    public function getUserByToken(Request $request, UserRepository $userRepository, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if(!$data['token']){
            return new JsonResponse(["tokenMsg" => "Invalid Token"]);
        }
        try {
            $userData = $jwtManager->parse($data['token']); // Use decode instead of parse
            if (!$userData || !isset($userData['username'])) {
                return new JsonResponse(["InvalidToken" => "Invalid Token"]);
            }
        } catch (\Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException $e) {
            return new JsonResponse(["ExpiredToken" => "Invalid or Expired Token"]);
        } catch (\Exception $e) {
            // Handle any other exceptions
            return new JsonResponse(["InvalidToken" => "An error occurred while processing the token"]);
        }

        $user = $userRepository->findOneBy(['email' => $userData['username']]);


        if (!$user) {
            return new JsonResponse(['InvalidToken' => 'User not found.']);
        }

        $team = $user->getTeam();
        $teamDetails = null;
        $members = [];

        if ($team) {
            $teamDetails = [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription(),
            ];

            foreach ($team->getUsers() as $member) {
                    $members[] = [
                        'id' => $member->getId(),
                        'email' => $member->getEmail(),
                        'name' => $member->getFullName(),
                        'role' => $member->getRoles(),
                    ];
                // }
            }
        }

        $userDetails = [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles(),
            'phoneNumber' => $user->getPhoneNumber(),
        ];

        $responseArray = [
            'user' => $userDetails,
            'team' => $teamDetails,
            'members' => $members,
        ];

        return new JsonResponse($responseArray, Response::HTTP_OK);
    }

    #[Route('/getUser/{id}', name: 'get_user_by_id', methods: ['POST'])]
    public function getUserById(Request $request, int $id,UserRepository $userRepository, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if(!$data['token']){
            return new JsonResponse(["tokenMsg" => "Invalid Token"]);
        }
        try {
            $userData = $jwtManager->parse($data['token']); // Use decode instead of parse
            if (!$userData || !isset($userData['username'])) {
                return new JsonResponse(["InvalidToken" => "Invalid Token"]);
            }
        } catch (\Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException $e) {
            return new JsonResponse(["ExpiredToken" => "Invalid or Expired Token"]);
        } catch (\Exception $e) {
            // Handle any other exceptions
            return new JsonResponse(["InvalidToken" => "An error occurred while processing the token"]);
        }

        $Tokenuser = $userRepository->findOneBy(['email' => $userData['username']]);


        if (!$Tokenuser) {
            return new JsonResponse(['InvalidToken' => 'User not found.']);
        }

        
        $user = $userRepository->findOneBy(['id' => $id]);
        if(!$user){
            return new JsonResponse(['error' => 'User not found.']);
        }

        $team = $user->getTeam();
        $teamDetails = null;
        $members = [];

        if ($team) {
            $teamDetails = [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription(),
            ];

            foreach ($team->getUsers() as $member) {
                    $members[] = [
                        'id' => $member->getId(),
                        'email' => $member->getEmail(),
                        'name' => $member->getFullName(),
                        'role' => $member->getRoles(),
                    ];
                // }
            }
        }

        $userDetails = [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles(),
            'phoneNumber' => $user->getPhoneNumber(),
        ];

        $loggedUserDetails = [
            'id' => $Tokenuser->getId(),
            'name' => $Tokenuser->getFullName(),
            'email' => $Tokenuser->getEmail(),
            'role' => $Tokenuser->getRoles(),
            'phoneNumber' => $Tokenuser->getPhoneNumber(),
        ];

        $responseArray = [
            'user' => $userDetails,
            'loggedUser' => $loggedUserDetails,
            'team' => $teamDetails,
            'members' => $members,
        ];

        return new JsonResponse($responseArray, Response::HTTP_OK);
    }

    #[Route('/user', name: 'create_user', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['fullName']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFullName($data['fullName']);
        $user->setPhoneNumber($data['phoneNumber']);
        $plainPassword = $data['password'];
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->setRoles(["ROLE_USER"]);

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'User created successfully!',
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'role' => $user->getRoles(),
                'phoneNumber' => $user->getPhoneNumber(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/user/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(Request $request, int $id, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        if (isset($data['fullName'])) {
            $user->setFullName($data['fullName']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['password'])) {
            $plainPassword = $data['password'];
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }
        if (isset($data['phoneNumber'])) {
            $user->setPhoneNumber($data['phoneNumber']);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'User updated successfully!',
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'phoneNumber' => $user->getPhoneNumber(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/user/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'User deleted successfully.'], Response::HTTP_OK);
    }
}
