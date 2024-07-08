<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use Symfony\Component\Mime\Email;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;


class AdminController extends AbstractController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }


    #[Route('/getAdmin', name: 'app_admin', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $adminUsers = $userRepository->findBy(['role' => 'admin']);
        $adminUsersArray = [];

        foreach ($adminUsers as $adminUser) {
            $adminUsersArray[] = [
                'id' => $adminUser->getId(),
                'fullName' => $adminUser->getFullName(),
                'email' => $adminUser->getEmail(),
                'role' => $adminUser->getRole(),
                'phoneNumber' => $adminUser->getPhoneNumber(),
            ];
        }

        return new JsonResponse($adminUsersArray, Response::HTTP_OK);
    }

    #[Route('/admin/addAdmin', name: 'add_admin', methods: ['POST'])]
    public function addAdmin(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email']) || !isset($data['fullName']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }

        $admin = new User();
        $admin->setEmail($data['email']);
        $admin->setFullName($data['fullName']);
        $admin->setPhoneNumber($data['phoneNumber']);
        $plainPassword = $data['password'];
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $plainPassword);
        $admin->setPassword($hashedPassword);
        // $admin->setRole('admin');

        $em->persist($admin);
        $em->flush();

        return new JsonResponse([
            'message' => 'Admin user added successfully!',
            'admin' => [
                'id' => $admin->getId(),
                'fullName' => $admin->getFullName(),
                'email' => $admin->getEmail(),
                // 'role' => $admin->getRole(),
                'phoneNumber' => $admin->getPhoneNumber(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/admin/deleteAdmin', name: 'delete_admin_form', methods: ['DELETE'])]
    public function deleteAdminForm(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email'])) {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }

        $email = $data['email'];
        $user = $userRepository->findOneBy(['email' => $email, 'role' => 'admin']);

        if (!$user) {
            return new JsonResponse(['error' => 'Admin user not found.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'Admin user deleted successfully.'], Response::HTTP_OK);
    }

    #[Route('/admin/updateAdmin/{userId}', name: 'update_admin', methods: ['PUT'])]
    public function updateAdmin(Request $request, int $userId, EntityManagerInterface $em, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $admin = $userRepository->find($userId);

        if (!$admin || $admin->getRole() !== 'admin') {
            return new JsonResponse(['error' => 'Admin user not found.'], Response::HTTP_NOT_FOUND);
        }

        if (isset($data['fullName'])) {
            $admin->setFullName($data['fullName']);
        }
        if (isset($data['email'])) {
            $admin->setEmail($data['email']);
        }
        if (isset($data['password'])) {
            $plainPassword = $data['password'];
            $hashedPassword = $passwordHasher->hashPassword($admin, $plainPassword);
            $admin->setPassword($hashedPassword);
        }
        if (isset($data['phoneNumber'])) {
            $admin->setPhoneNumber($data['phoneNumber']);
        }

        $em->persist($admin);
        $em->flush();

        $responseData = [
            'message' => 'Admin user updated successfully!',
            'admin' => [
                'id' => $admin->getId(),
                'fullName' => $admin->getFullName(),
                'email' => $admin->getEmail(),
                'role' => $admin->getRole(),
                'phoneNumber' => $admin->getPhoneNumber(),
            ]
        ];

        return new JsonResponse($responseData, Response::HTTP_OK);
    }

    #[Route('/admin/getIndUsers', name: 'ind_user', methods: ['GET'])]
    public function getIndUsers(UserRepository $userRepository): JsonResponse
    {
        $indUsers = $userRepository->findBy(['team' => null]);
        $usersArray = [];
        foreach ($indUsers as $indUser) {
            $usersArray[] = [
                'id' => $indUser->getId(),
                'name' => $indUser->getFullName(),
                'email' => $indUser->getEmail(),
                'role' => $indUser->getRole(),
                'phone_number' => $indUser->getPhoneNumber(),
            ];
        }

        return new JsonResponse($usersArray, Response::HTTP_OK);
    }



    #[Route('/admin/inviteUser', name: 'invite_user', methods: ['POST'])]
    public function invite(Request $request,JWTTokenManagerInterface $jwtManager, EntityManagerInterface $em, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository): JsonResponse {

        $data = json_decode($request->getContent(), true);
        if (!isset($data['token'])) {
            return new JsonResponse(['error' => 'Token is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userData = $jwtManager->parse($data['token']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
        }

        // Check if the user exists in the database
        $user = $userRepository->findOneBy(['email' => $userData['username']]);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        // Step 3: Verify if the user's role is "admin"
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Proceed with the invitation process if the checks pass
        if (isset($data['email']) && isset($data['fullName'])) {
            $email = $data['email'];
            $fullName = $data['fullName'];

            $existingUser = $userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
            }

            $dummyPassword = bin2hex(random_bytes(4));
            $newUser = new User();

            $hashedPassword = $passwordHasher->hashPassword($newUser, $dummyPassword);
            $newUser->setPassword($hashedPassword);
            $newUser->setEmail($email);
            $newUser->setFullName($fullName);
            $newUser->setRoles(["ROLE_USER"]);

            $em->persist($newUser);
            $em->flush();

            $emailMessage = (new Email())
                ->from('taskboard.08@gmail.com')
                ->to($email)
                ->subject('You are invited as a user')
                ->html($this->renderView('emails/invite.html.twig', [
                    'email' => $email,
                    'password' => $dummyPassword,
                ]));

            $mailer->send($emailMessage);

            return new JsonResponse([
                'message' => 'Invitation sent successfully!',
                'user' => [
                    'id' => $newUser->getId(),
                    'email' => $newUser->getEmail(),
                    'name' => $newUser->getFullName(),
                ]
            ], Response::HTTP_OK);
        }

        return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
    }






    #[Route('/admin/createTeam', name: 'create_team', methods: ['POST'])]
    public function createTeam(Request $request, EntityManagerInterface $em, TeamRepository $teamRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['teamName']) && isset($data['teamDescription'])) {
            $existingTeam = $teamRepository->findOneBy(['name' => $data['teamName']]);
            if ($existingTeam) {
                return new JsonResponse([
                    'error' => 'A team with this name already exists.',
                    'team' => [
                        'id' => $existingTeam->getId(),
                        'name' => $existingTeam->getName(),
                        'description' => $existingTeam->getDescription(),
                        'createdAt' => $existingTeam->getCreatedAt()
                    ]
                ], Response::HTTP_BAD_REQUEST);
            } else {
                try {
                    $team = new Team();
                    $team->setName($data['teamName']);
                    $team->setDescription($data['teamDescription']);

                    $em->persist($team);
                    $em->flush();

                    return new JsonResponse([
                        'message' => 'Team created successfully!',
                        'team' => [
                            'id' => $team->getId(),
                            'name' => $team->getName(),
                            'description' => $team->getDescription(),
                            'createdAt' => $team->getCreatedAt()
                        ]
                    ], Response::HTTP_OK);
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Error creating team'], Response::HTTP_BAD_REQUEST);
                }
            }
        } else {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }
    }


    #[Route('/admin/getTeams', name: 'show_teams', methods: ['GET'])]
    public function showTeams(TeamRepository $teamRepository, UserRepository $userRepository): JsonResponse
    {
        $teams = $teamRepository->findAll();
        $teamsArray = [];

        foreach ($teams as $team) {
            $users = $userRepository->findBy(['team' => $team]);
            $userCount = count($users);
            $teamsArray[] = [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'count' => $userCount,
                'createdAt' => $team->getCreatedAt(),
                'description' => $team->getDescription()
            ];
        }

        return new JsonResponse($teamsArray, Response::HTTP_OK);
    }

    #[Route('/admin/getTeam/{id}', name: 'show_team', methods: ['GET'])]
    public function showTeam(int $id, TeamRepository $teamRepository, UserRepository $userRepository): JsonResponse
    {
        $team = $teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('The team does not exist');
        }

        $users = $userRepository->findBy(['team' => $team->getId()]);
        $userCount = count($users);

        $data = [
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription()
            ],
            'users' => [],
            'userCount' => $userCount,
        ];

        foreach ($users as $user) {
            $data['users'][] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getFullName(),
                'role' => $user->getRole()
            ];
        }

        return new JsonResponse($data);
    }


    #[Route('/admin/deleteTeam/{teamId}', name: 'delete_team', methods: ['DELETE'])]
    public function deleteTeam(int $teamId, EntityManagerInterface $em, TeamRepository $teamRepository): JsonResponse
    {
        $team = $teamRepository->find($teamId);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            foreach ($team->getUsers() as $user) {
                $user->setTeam(null);
            }

            $em->remove($team);
            $em->flush();

            return new JsonResponse(['message' => 'Team deleted successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            error_log('Failed to delete team with ID ' . $teamId . ': ' . $e->getMessage());

            return new JsonResponse(['error' => 'Failed to delete team.'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/admin/updateTeam/{teamId}', name: 'update_team', methods: ['PUT'])]
    public function updateTeam(Request $request, int $teamId, EntityManagerInterface $em, TeamRepository $teamRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $team = $teamRepository->find($teamId);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.'], Response::HTTP_NOT_FOUND);
        }

        if (isset($data['teamName'])) {
            $team->setName($data['teamName']);
        }
        if (isset($data['teamDescription'])) {
            $team->setDescription($data['teamDescription']);
        }

        $em->persist($team);
        $em->flush();


        $responseData = [
            'message' => 'Team updated successfully!',
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription(),
                'createdAt' => $team->getCreatedAt(),
            ]
        ];

        return new JsonResponse($responseData, Response::HTTP_OK);
    }


    #[Route('/admin/addUserToTeam/{team_id}', name: 'add_user_to_team', methods: ['POST'])]
    public function addUserToTeam(Request $request, EntityManagerInterface $em, UserRepository $userRepository, int $team_id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
            return new JsonResponse(['error' => 'Invalid request format. Expected user_ids array.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $team = $em->getRepository(Team::class)->find($team_id);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        foreach ($data['user_ids'] as $userId) {
            $user = $userRepository->find($userId);

            if ($user && !$user->getTeam()) {
                $user->setTeam($team);
                $em->persist($user);
            }
        }

        $em->flush();

        return new JsonResponse(['message' => 'Users added successfully to team']);
    }

    #[Route('/admin/removeUserFromTeam/{teamId}/{userId}', name: 'remove_user_from_team', methods: ['POST'])]
    public function removeUserFromTeam(int $teamId, int $userId, EntityManagerInterface $em, UserRepository $userRepository, TeamRepository $teamRepository): JsonResponse
    {
        $user = $userRepository->find($userId);
        $team = $teamRepository->find($teamId);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($user->getTeam() !== $team) {
            throw $this->createAccessDeniedException('User does not belong to this team');
        }

        $user->setTeam(null);
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User removed from the team successfully!']);
    }

    #[Route('/admin/toggleRole/{userId}', name: 'update_user_role', methods: ['POST'])]
    public function updateUserRole(int $userId, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Toggle user role
        if ($user->getRole() == 'user') {
            $user->setRole('admin');
        } else {
            $user->setRole('user');
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User role toggled successfully!']);
    }
    #[Route('/admin/changeUserTeam/{userId}', name: 'change_user_team', methods: ['POST'])]
    public function changeUserTeam(Request $request, EntityManagerInterface $em, UserRepository $userRepository, int $userId): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['teamId'])) {
            return new JsonResponse(['error' => 'Team ID is required.'], Response::HTTP_BAD_REQUEST);
        }

        $teamId = $data['teamId'];
        $team = $em->getRepository(Team::class)->find($teamId);
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Remove user from current team (if any)
            $currentUserTeam = $user->getTeam();
            if ($currentUserTeam) {
                $currentUserTeam->removeUser($user);
            }

            // Add user to the new team
            $user->setTeam($team);
            $team->addUser($user);

            $em->persist($user);
            $em->persist($team);
            $em->flush();

            return new JsonResponse(['message' => 'User team updated successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to update user team.'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/admin/getUserTeamMember/{userId}', name: 'get_user_team_member', methods: ['GET'])]
    public function getUserTeamMember(int $userId, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $team = $user->getTeam();

        if (!$team) {
            return new JsonResponse(['error' => 'User is not part of any team.'], Response::HTTP_NOT_FOUND);
        }

        $members = [];
        foreach ($team->getUsers() as $member) {
            // Exclude the user themselves from the list of members
            if ($member->getId() !== $userId) {
                $members[] = [
                    'id' => $member->getId(),
                    'email' => $member->getEmail(),
                    'name' => $member->getFullName(),
                    'role' => $member->getRole(),
                ];
            }
        }

        $response = [
            'user' => [
                'id' => $user->getId(),
                'name' =>  $user->getName(),
                'email' => $user->getEmail(),
                
            ],
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'description' => $team->getDescription(),
            ],
            'members' => $members,
        ];

        return new JsonResponse($response, Response::HTTP_OK);
    }
}
