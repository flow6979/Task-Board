<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Email is required.']);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['error' => 'No user found with that email address.']);
        }

        $resetToken = bin2hex(random_bytes(32));
        $user->setResetToken($resetToken);
        $entityManager->flush();

        $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken],UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from('taskboard.08@gmail.com')
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html($this->renderView('emails/reset_password.html.twig', [
                'resetUrl' => $resetUrl
            ]));

        $mailer->send($email);

        return new JsonResponse(['message' => 'Password reset link sent to your email.']);
    }
}