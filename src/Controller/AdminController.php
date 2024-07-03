<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminType;
use App\Form\InviteType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(UserRepository $userRepository): Response
    {
        $adminUser = $userRepository->findOneBy(['role' => 'admin']);

        return $this->render('admin/index.html.twig', [
            'admin_user' => $adminUser,
        ]);
    }

    #[Route('/admin/add', name: 'add_admin')]
    public function addAdmin(Request $request, EntityManagerInterface $em): Response
    {
        $admin = new User();
        $form = $this->createForm(AdminType::class, $admin);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $admin->setRole('admin');
            $em->persist($admin);
            $em->flush();

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/invite', name: 'invite_admin')]
    public function invite(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(InviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $dummyPassword = bin2hex(random_bytes(4)); // Generates a random 8-character string
            $user = new User();
            // $encodedPassword = $passwordHasher->hashPassword($user, $dummyPassword);

            
            $user->setEmail($email);
            $user->setPassword($dummyPassword);
            $user->setRole('user');
            $em->persist($user);
            $em->flush();

            $emailMessage = (new Email())
                ->from('noreply@example.com')
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
}
