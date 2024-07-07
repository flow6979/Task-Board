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

class UserController extends AbstractController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/user', name: 'user_dashboard')]
    public function index(): Response
    {
        return $this->render('user_page/index.html.twig');
    }

    // #[Route('/user', name: 'get_all_users', methods: ['GET'])]
    // public function getAllUsers(UserRepository $userRepository): JsonResponse
    // {
    //     $users = $userRepository->findAll();
    //     $usersArray = [];

    //     foreach ($users as $user) {
    //         $usersArray[] = [
    //             'id' => $user->getId(),
    //             'name' => $user->getFullName(),
    //             'email' => $user->getEmail(),
    //             'role' => $user->getRole(),
    //             'phoneNumber' => $user->getPhoneNumber(),
    //         ];
    //     }

    //     return new JsonResponse($usersArray, Response::HTTP_OK);
    // }

    #[Route('/getUser/{id}', name: 'get_user_by_id', methods: ['GET'])]
    public function getUserById(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
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
                // Exclude the user themselves from the list of members
                // if ($member->getId() !== $id) {
                    $members[] = [
                        'id' => $member->getId(),
                        'email' => $member->getEmail(),
                        'name' => $member->getFullName(),
                        'role' => $member->getRole(),
                    ];
                // }
            }
        }

        $userDetails = [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'phoneNumber' => $user->getPhoneNumber(),
        ];

        $responseArray = [
            'user' => $userDetails,
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
        $user->setRole('user');

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'User created successfully!',
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
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
