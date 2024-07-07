<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface

{
    use TargetPathTrait;
    private JWTTokenManagerInterface $jwtManager;
    private LoggerInterface $logger;
    private UserProviderInterface $userProvider;


    // public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response

    // public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    // {
    //     dd('sdfsd');
    //     // $user = $event->getUser();
    //         // $jwt = $this->jwtManager->create($user);

    //         // $data = $event->getData();
    //         // $data['token'] = $jwt;
    //         // $token = $this->jwtManager->create($user);
    //         // $this->logger->info('Generated JWT token', ['token' => $token]);

    //         // if (in_array('ROLE_ADMIN', $user->getRoles())) {
    //         //     $data['redirect'] = '/api/admin';
    //         // } else {
    //         //     $data['redirect'] = '/api/home';
    //         // }

    //         // $data = $event->getData();
    //         // $user = $event->getUser();

    //         // if (!$user instanceof UserInterface) {
    //         //     return;
    //         // }

    //         // $token = $this->jwtManager->create($user);
    //         // $this->logger->info('Generated JWT token', ['token' => $token]);

    //         // $data['token'] = $token;
    //         // $data['redirect'] = in_array('ROLE_ADMIN', $user->getRoles()) ? '/api/admin' : '/api/home';

    //     // $event->setData($data);

    //     $user = $event->getUser();
    //     $jwt = $this->jwtManager->create($user);

    //     $data = $event->getData();
    //     $data['token'] = $jwt;

    //     if (in_array('ROLE_ADMIN', $user->getRoles())) {
    //         $data['redirect'] = '/api/admin';
    //     } else {
    //         $data['redirect'] = '/api/home';
    //     }

    //     $event->setData($data);
    // }
    

    public function __construct(JWTTokenManagerInterface $jwtManager, LoggerInterface $logger, UserProviderInterface $userProvider, private UrlGeneratorInterface $urlGenerator, private EntityManagerInterface $entityManager)
    {
     
        $this->jwtManager = $jwtManager;
        $this->logger = $logger;
        $this->userProvider = $userProvider;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

        return new Passport(
         new UserBadge($email, function ($userIdentifier) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userIdentifier]);
                if (!$user) {
                    // try with email matching
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userIdentifier]);
                }
                if (!$user) {
                    return $this->invalidCredentialsMessage();
                }
                $reversedRoles = ['ROLE_USER', 'ROLE_ADMIN'];
                if (array_intersect($reversedRoles, $user->getRoles())) {
                    throw new CustomUserMessageAuthenticationException('This account is not registered with us. Please create an account.');
                }

                if (!array_intersect($reversedRoles, $user->getRoles()) && !array_intersect(['ROLE_CUSTOMER'], $user->getRoles())) {
                    throw new CustomUserMessageAuthenticationException('This account is not registered with us. Please create an account.');
                }

                return $user;
                
            }),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $targetUrl = $this->urlGenerator->generate('admin_dashboard');
        } else {
            $targetUrl = $this->urlGenerator->generate('user_dashboard');
        }

        return new RedirectResponse($targetUrl);
    }


    private function invalidCredentialsMessage()
    {
        throw new CustomUserMessageAuthenticationException('Invalid credentials, please try again, click Forgot Password? or email ' . $supportEmail['supportEmail'] . ' for assistance.');
    }
}