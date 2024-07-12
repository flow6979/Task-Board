<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use Symfony\Component\Mime\Email;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
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
    private $logger;


    public function __construct(UserPasswordHasherInterface $passwordHasher, LoggerInterface $logger)
    {
        $this->passwordHasher = $passwordHasher;
        $this->logger = $logger;

    }


    #[Route('/getAdmin', name: 'app_admin', methods: ['POST'])]
    public function index(Request $request,
    JWTTokenManagerInterface $jwtManager,UserRepository $userRepository): JsonResponse
    {
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
        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }

        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }


        // $adminUsers = $userRepository->findBy(['roles' => ['ROLE_ADMIN']]);
        $adminUsers = $userRepository->findAdminUsers();

        $adminUsersArray = [];

        foreach ($adminUsers as $adminUser) {
            // Initialize default values for team data
            $teamData = null;
        
            // Check if the team data exists before accessing it
            if ($adminUser->getTeam() !== null) {
                $teamData = [
                    'id' => $adminUser->getTeam()->getId(),
                ];
            }
        
            // Append user data with possibly null team data
            $adminUsersArray[] = [
                'id' => $adminUser->getId(),
                'name' => $adminUser->getFullName(),
                'email' => $adminUser->getEmail(),
                'role' => $adminUser->getRoles(),
                'phoneNumber' => $adminUser->getPhoneNumber(),
                'team' => $teamData
            ];
        }
        

        return new JsonResponse($adminUsersArray, Response::HTTP_OK);
    }

    #[Route('/admin/addAdmin', name: 'add_admin', methods: ['POST'])]
    public function addAdmin(Request $request, EntityManagerInterface $em,
    JWTTokenManagerInterface $jwtManager, UserRepository $userRepository): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["msg"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $existingUser = $userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email already exists']);
        }
        $admin = new User();
        $admin->setEmail($data['email']);
        $admin->setFullName($data['fullName']);
        $admin->setPhoneNumber($data['phoneNumber']);
        $plainPassword = $data['password'];
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $plainPassword);
        $admin->setPassword($hashedPassword);
        $admin->setRoles(['ROLE_ADMIN']);

        $em->persist($admin);
        $em->flush();

        return new JsonResponse([
            'message' => 'Admin user added successfully!',
            'admin' => [
                'id' => $admin->getId(),
                'fullName' => $admin->getFullName(),
                'email' => $admin->getEmail(),
                 'role' => $admin->getRoles(),
                'phoneNumber' => $admin->getPhoneNumber(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/admin/deleteAdmin', name: 'delete_admin_form', methods: ['DELETE'])]
    public function deleteAdminForm(Request $request, EntityManagerInterface $em, UserRepository $userRepository, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }

        $email = $data['email'];
        $user = $userRepository->findOneBy(['email' => $email]);
        // $user = $userRepository->findAdminUsers();


        if (!$user) {
            return new JsonResponse(['error' => 'Admin user not found.']);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'Admin user deleted successfully.'], Response::HTTP_OK);
    }

    #[Route('/admin/updateAdmin/{userId}', name: 'update_admin', methods: ['PUT'])]
    public function updateAdmin(Request $request, int $userId, EntityManagerInterface $em, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher,JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $admin = $userRepository->find($userId);

        if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles())) {
            return new JsonResponse(['error' => 'Admin user not found.']);
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
                'role' => $admin->getRoles(),
                'phoneNumber' => $admin->getPhoneNumber(),
            ]
        ];

        return new JsonResponse($responseData, Response::HTTP_OK);
    }

    #[Route('/admin/getIndUsers', name: 'ind_user', methods: ['POST'])]
    public function getIndUsers(UserRepository $userRepository, JWTTokenManagerInterface $jwtManager, Request $request): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $indUsers = $userRepository->findBy(['team' => null]);
        $usersArray = [];
        foreach ($indUsers as $indUser) {
            $usersArray[] = [
                'id' => $indUser->getId(),
                'name' => $indUser->getFullName(),
                'email' => $indUser->getEmail(),
                'role' => $indUser->getRoles(),
                'phone_number' => $indUser->getPhoneNumber(),

            ];
        }

        return new JsonResponse($usersArray, Response::HTTP_OK);
    }



    #[Route('/admin/inviteUser', name: 'invite_user', methods: ['POST'])]
    public function invite(Request $request,JWTTokenManagerInterface $jwtManager, EntityManagerInterface $em, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository): JsonResponse {
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

        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['accessStatus' => 'Access denied']);
        }        

        if (isset($data['email']) && isset($data['fullName'])) {
            $email = $data['email'];
            $fullName = $data['fullName'];

            $existingUser = $userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                return new JsonResponse(['error' => 'Email already exists']);
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

        return new JsonResponse(['error' => 'Invalid input']);
    }






    #[Route('/admin/createTeam', name: 'create_team', methods: ['POST'])]
    public function createTeam(Request $request, EntityManagerInterface $em, TeamRepository $teamRepository, UserRepository $userRepository,JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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
        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }

        if (isset($data['teamName']) && isset($data['teamDescription'])) {
            $existingTeam = $teamRepository->findOneBy(['name' => $data['teamName']]);
            if ($existingTeam) {
                return new JsonResponse([
                    'error' => 'A team with this name already exists.'
                ] );
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
                            'count' =>0,
                            'name' => $team->getName(),
                            'description' => $team->getDescription(),
                            'createdAt' => $team->getCreatedAt()
                        ]
                    ], Response::HTTP_OK);
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Error creating team']);
                }
            }
        } else {
            return new JsonResponse(['error' => 'Invalid input']);
        }
    }


    #[Route('/admin/getTeams', name: 'show_teams', methods: ['POST'])]
    public function showTeams(Request $request, TeamRepository $teamRepository, JWTTokenManagerInterface $jwtManager, UserRepository $userRepository): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
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

    #[Route('/admin/getTeam/{id}', name: 'show_team', methods: ['POST'])]
    public function showTeam(Request $request, int $id, TeamRepository $teamRepository, JWTTokenManagerInterface $jwtManager,UserRepository $userRepository): JsonResponse
    {
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
        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }

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
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getFullName(),
                'role' => $user->getRoles()
            ],
            'users' => [],
            'userCount' => $userCount,
        ];

        foreach ($users as $user) {
            $data['users'][] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getFullName(),
                'role' => $user->getRoles()
            ];
        }

        return new JsonResponse($data);
    }


    #[Route('/admin/deleteTeam/{teamId}', name: 'delete_team', methods: ['DELETE'])]
    public function deleteTeam(Request $request,int $teamId, EntityManagerInterface $em,JWTTokenManagerInterface $jwtManager,UserRepository $userRepository, TeamRepository $teamRepository): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $team = $teamRepository->find($teamId);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.']);
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

            return new JsonResponse(['error' => 'Failed to delete team.']);
        }
    }

    #[Route('/admin/updateTeam/{teamId}', name: 'update_team', methods: ['PUT'])]
    public function updateTeam(Request $request, int $teamId, EntityManagerInterface $em, JWTTokenManagerInterface $jwtManager,TeamRepository $teamRepository,UserRepository $userRepository): JsonResponse
    {
        
    
        // if (!isset($data['email'])) {
        //     return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        // }
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $team = $teamRepository->find($teamId);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.']);
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
    public function addUserToTeam(Request $request, EntityManagerInterface $em,JWTTokenManagerInterface $jwtManager, UserRepository $userRepository, int $team_id): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }

        if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
            return new JsonResponse(['error' => 'Invalid request format. Expected user_ids array.']);
        }
        $team = $em->getRepository(Team::class)->find($team_id);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.']);
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
    public function removeUserFromTeam(Request $request,int $teamId, int $userId, EntityManagerInterface $em,JWTTokenManagerInterface $jwtManager, UserRepository $userRepository, TeamRepository $teamRepository): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $user = $userRepository->find($userId);
        $team = $teamRepository->find($teamId);

        if (!$user) {
            return new JsonResponse(["error" => "User not found"]);
            // throw $this->createNotFoundException('User not found');
        }

        if (!$team) {
            return new JsonResponse(["error" => "Team not found"]);
            // throw $this->createNotFoundException('Team not found');
        }

        if ($user->getTeam() !== $team) {
            return new JsonResponse(["error" => "User does not belong to this team"]);
            // throw $this->createAccessDeniedException('User does not belong to this team');
        }

        $user->setTeam(null);
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User removed from the team successfully!']);
    }

    #[Route('/admin/toggleRole/{userId}', name: 'update_user_role', methods: ['POST'])]
    public function updateUserRole(Request $request,int $userId, EntityManagerInterface $em, UserRepository $userRepository,JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        $user = $userRepository->find($userId);

        if (!$user) {
            // throw $this->createNotFoundException('User not found');
            return new JsonResponse(["error" => "User not found"]);
        }

        // Toggle user role
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $user->setRoles(['ROLE_ADMIN','ROLE_USER']);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User role toggled successfully!','roles' => $user->getRoles()]);
    }
    #[Route('/admin/changeUserTeam/{userId}', name: 'change_user_team', methods: ['POST'])]
    public function changeUserTeam(Request $request, EntityManagerInterface $em, UserRepository $userRepository, int $userId, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }
        if (!isset($data['teamId'])) {
            return new JsonResponse(['error' => 'Team ID is required.']);
        }

        $teamId = $data['teamId'];
        $team = $em->getRepository(Team::class)->find($teamId);
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.']);
        }

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.']);
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

            return new JsonResponse(['message' => 'User team updated successfully.']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to update user team.']);
        }
    }

    #[Route('/admin/getUserTeamMember/{userId}', name: 'get_user_team_member', methods: ['GET'])]
    public function getUserTeamMember(Request $request,int $userId, UserRepository $userRepository, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
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

    

        if(!in_array('ROLE_ADMIN', $user->getRoles()))
        {
            return new JsonResponse(["accessStatus"=>"Access Denied"]);
        }
        
        if (!$user) {
            return new JsonResponse(["InvalidToken" => "Invalid Token"]);
        }

        try {
            $user = $userRepository->find($userId);

            if (!$user) {
                return new JsonResponse(['error' => 'User not found.']);
            }

            $team = $user->getTeam();

            if (!$team) {
                return new JsonResponse(['error' => 'User is not part of any team.']);
            }

            $members = [];
            foreach ($team->getUsers() as $member) {
                // Exclude the user themselves from the list of members
                if ($member->getId() !== $userId) {
                    $members[] = [
                        'id' => $member->getId(),
                        'email' => $member->getEmail(),
                        'name' => $member->getFullName(),
                        'role' => $member->getRoles(),
                    ];
                }
            }

            $response = [
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                    'description' => $team->getDescription(),
                ],
                'members' => $members,
            ];

            return new JsonResponse($response, Response::HTTP_OK);
        } catch (\Exception $e) {

            $this->logger->error('An error occurred ' . $e->getMessage());
            return new JsonResponse(['error' => 'An error occurred ']);
        }
    }
}
