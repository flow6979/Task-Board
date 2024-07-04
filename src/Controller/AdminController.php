<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use App\Form\AdminDeleteType;
use App\Form\AdminType;
use App\Form\InviteType;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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

    #[Route('/admin/inviteUser', name: 'invite_user')]
    public function invite(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $form = $this->createForm(InviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $fullName = $form->get('fullName')->getData();

            $dummyPassword = bin2hex(random_bytes(4)); // Generates a random 8-character string
            $user = new User();
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dummyPassword);
            $user->setPassword($hashedPassword);

            
            $user->setEmail($email);
            $user->setFullName($fullName);

            $user->setPassword($hashedPassword);
            $user->setRole('user');
            $em->persist($user);
            $em->flush();

            $emailMessage = (new Email())
                ->from('vaishnavi22kahar@gmail.com')
                ->to($email)
                ->subject('You are invited as an admin')
                ->html($this->renderView('emails/invite.html.twig', [
                    'email' => $email,
                    'password' => $dummyPassword,
                ]));

            $mailer->send($emailMessage);

            $this->addFlash('success', 'Invitation sent successfully!');

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/invite.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/createTeam', name: 'create_team')]
    public function createTeam(Request $request, EntityManagerInterface $em): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Team created successfully!');

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/create_team.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/showTeams', name: 'show_teams')]
    public function showTeams(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findAll();

        return $this->render('admin/show_teams.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/admin/showTeam/{id}', name: 'show_team')]
    public function showTeam(int $id, TeamRepository $teamRepository): Response
    {
        $team = $teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('The team does not exist');
        }

        return $this->render('admin/show_team.html.twig', [
            'team' => $team,
        ]);
    }

}
