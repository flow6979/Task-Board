<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use App\Form\TeamType;
use App\Form\AdminType;
use App\Form\InviteType;
use App\Form\AdminDeleteType;
use App\Form\AddUserToTeamType;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminController extends AbstractController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    #[Route('/admin', name: 'app_admin')]
    public function index(UserRepository $userRepository): Response
    {
        $adminUser = $userRepository->findBy(['role' => 'admin']);

        return $this->render('admin/index.html.twig', [
            'admin_users' => $adminUser,
        ]);
    }
    #[Route('/admin/getIndUsers', name: 'ind_user')]
    public function getIndUsers(UserRepository $userRepository): Response
    {
        $indUsers = $userRepository->findBy(['team' => null]);
        $usersArray = [];
        foreach ($indUsers as $indUser) {
            // $users = $userRepository->findBy(['team' => $team]);
            // $userCount = count($users);
            //             id
            // | team_id
            // | email        | 
            // | password     | 
            // | full_name    | 
            // | role         | 
            // | phone_number
            $usersArray[] = [
                'id' => $indUser->getId(),
                'name' => $indUser->getFullName(),
                'email' => $indUser->getEmail(),
                'role' => $indUser->getRole(),
                'phone_number' => $indUser->getPhoneNumber(),
            ];
        }

        // Uncomment the following line if you want to return JSON response
        return new Response(json_encode($usersArray), 200, ['Content-Type' => 'application/json']);
    }


    #[Route('/admin/addAdmin', name: 'add_admin')]
    public function addAdmin(Request $request, EntityManagerInterface $em): Response
    {
        $admin = new User();
        $form = $this->createForm(AdminType::class, $admin);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Retrieve the plain password from the form data
            $plainPassword = $form->get('password')->getData();

            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($admin, $plainPassword);

            // Set the hashed password to the User entity
            $admin->setPassword($hashedPassword);
            $admin->setRole('admin');

            $em->persist($admin);
            $em->flush();

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/admin/deleteAdmin', name: 'delete_admin_form')]
    public function deleteAdminForm(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $form = $this->createForm(AdminDeleteType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $email = $formData['email'];

            // Find admin user by email
            $user = $userRepository->findOneBy(['email' => $email, 'role' => 'admin']);

            if (!$user) {
                $this->addFlash('error', 'Admin user not found.');
            } else {
                // Delete admin user
                $em->remove($user);
                $em->flush();

                $this->addFlash('success', 'Admin user deleted successfully.');
            }

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/delete_admin_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // #[Route('/admin/inviteUser', name: 'invite_user')]
    // public function invite(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    // {
    //     $form = $this->createForm(InviteType::class);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $email = $form->get('email')->getData();
    //         $fullName = $form->get('fullName')->getData();

    //         $dummyPassword = bin2hex(random_bytes(4));
    //         $user = new User();
    //         // Hash the password
    //         $hashedPassword = $this->passwordHasher->hashPassword($user, $dummyPassword);
    //         $user->setPassword($hashedPassword);


    //         $user->setEmail($email);
    //         $user->setFullName($fullName);

    //         $user->setPassword($hashedPassword);
    //         $user->setRole('user');
    //         $em->persist($user);
    //         $em->flush();

    //         $emailMessage = (new Email())
    //             ->from('vaishnavi22kahar@gmail.com')
    //             ->to($email)
    //             ->subject('You are invited as an user')
    //             ->html($this->renderView('emails/invite.html.twig', [
    //                 'email' => $email,
    //                 'password' => $dummyPassword,
    //             ]));

    //         $mailer->send($emailMessage);

    //         $this->addFlash('success', 'Invitation sent successfully!');

    //         return $this->redirectToRoute('app_admin');
    //     }

    //     return $this->render('admin/invite.html.twig', [
    //         'form' => $form->createView(),
    //     ]);
    // }

    #[Route('/admin/inviteUser', name: 'invite_user', methods: ['POST'])]
    public function invite(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher): Response
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email']) && isset($data['fullName'])) {
            $email = $data['email'];
            $fullName = $data['fullName'];

            $dummyPassword = bin2hex(random_bytes(4));
            $user = new User();

            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword($user, $dummyPassword);
            $user->setPassword($hashedPassword);
            $user->setEmail($email);
            $user->setFullName($fullName);
            $user->setRole('user');

            $em->persist($user);
            $em->flush();

            $emailMessage = (new Email())
                ->from('vaishnavi22kahar@gmail.com')
                ->to($email)
                ->subject('You are invited as an user')
                ->html($this->renderView('emails/invite.html.twig', [
                    'email' => $email,
                    'password' => $dummyPassword,
                ]));

            $mailer->send($emailMessage);

            return new JsonResponse([
                'message' => 'Invitation sent successfully!',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getFullName(),
                    'role' => $user->getRole()
                ]
            ], Response::HTTP_OK);
        }

        return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/admin/createTeam', name: 'create_team', methods: ['POST'])]
    public function createTeam(Request $request, EntityManagerInterface $em, TeamRepository $teamRepository): Response
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['teamName']) && isset($data['teamDescription'])) {
            $existingTeam = $teamRepository->findOneBy(['name' => $data['teamName']]);
            if ($existingTeam) {
                return new JsonResponse(['error' => 'A team with this name already exists.'], Response::HTTP_BAD_REQUEST);
            } else {
                try {
                    $team = new Team();

                    $team->setName($data['teamName']);
                    $team->setDescription($data['teamDescription']);

                    $em->persist($team);
                    $em->flush();

                    // $this->addFlash('success', 'Team created successfully!');
                    return new JsonResponse([
                        'message' => 'Invitation sent successfully!',
                        'team' => [
                            'id' => $team->getId(),
                            'name' => $team->getName(),
                            'description' => $team->getDescription(),
                            'createdAt' => $team->getCreatedAt()
                        ]
                    ], Response::HTTP_OK);
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'error creating team'], Response::HTTP_BAD_REQUEST);
                }
            }
        } else {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }
    }


    #[Route('/admin/getTeams', name: 'show_teams')]
    public function showTeams(TeamRepository $teamRepository, UserRepository $userRepository): Response
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

        return new Response(json_encode($teamsArray), 200, ['Content-Type' => 'application/json']);
    }




    #[Route('/admin/showTeam/{id}', name: 'show_team')]
    public function showTeam(int $id, TeamRepository $teamRepository, UserRepository $userRepository): JsonResponse
    {
        $team = $teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('The team does not exist');
        }

        $users = $userRepository->findBy(['team' => $team->getId()]);
        $userCount = count($users);

        // Serialize data to array
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

        // Return JSON response
        return new JsonResponse($data);
    }


    #[Route('/admin/addUserToTeam/{team_id}', name: 'add_user_to_team',methods:['POST'])]
    public function addUserToTeam(Request $request, EntityManagerInterface $em, UserRepository $userRepository, int $team_id): JsonResponse
    {
        // Parse JSON data from request body
        $data = json_decode($request->getContent(), true);

        //Validate data format (ensure user_ids array exists)
        if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
            return new JsonResponse(['error' => 'Invalid request format. Expected user_ids array.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // // Fetch the team object (assuming team is identified elsewhere in the request)
        // $teamId = $data['team_id']; // Assuming 'team_id' is part of the request data
        $team = $em->getRepository(Team::class)->find($team_id);

        if (!$team) {
            return new JsonResponse(['error' => 'Team not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        foreach ($data['user_ids'] as $userId) {
            $user = $userRepository->find($userId);
    
            if ($user && !$user->getTeam()) { // Check if user exists and is not already in a team
                $user->setTeam($team);
                $em->persist($user);
            }
        }

        $em->flush();

        return new JsonResponse(['message'=>'Users added succesfully to team']);
    }


    #[Route('/admin/removeUserFromTeam/{teamId}/{userId}', name: 'remove_user_from_team')]
    public function removeUserFromTeam(int $teamId, int $userId, EntityManagerInterface $em, UserRepository $userRepository, TeamRepository $teamRepository): Response
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


        return new JsonResponse(['message'=>'User removed from the team successfully!']);
    }
    
    #[Route('/admin/toggleRole/{userId}', name: 'update_user_role')]
    public function updateUserRole( int $userId, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Update user role to admin
        if($user->getRole()=='user'){
            $user->setRole('admin');
        }else{
            $user->setRole('user');
        }
        
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message'=>'User role toggled successfully!']);
    }
}
