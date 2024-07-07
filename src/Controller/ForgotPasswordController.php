<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Form\ForgotPasswordType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        // $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
         //$resetToken = bin2hex(random_bytes(32));
        // $user->setResetToken($resetToken);
        // $entityManager->flush();

        // $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);
        //dd($resetUrl);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $resetToken = bin2hex(random_bytes(32));
                $user->setResetToken($resetToken);
                $entityManager->flush();

                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                $email = (new Email())
                    ->from('taskboard.08@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Password Reset Request')
                    ->html("<p>Click the following link to reset your password: <a href=\"{$resetUrl}\">{$resetUrl}</a></p>");

                $mailer->send($email);

                $this->addFlash('success', 'Password reset link sent to your email.');
                 return $this->redirectToRoute('app_login');
            } else {
                $this->addFlash('error', 'No user found with that email address.');
            }
        }

        return $this->render('forgot_password/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
