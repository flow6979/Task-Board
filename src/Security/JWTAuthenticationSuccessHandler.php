<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

class JWTAuthenticationSuccessHandler

// class JWTAuthenticationSuccessHandler

{
    public function supports(Request $request): ?bool
    {
        // Implement logic to check if this authenticator supports the given request
        // For example, check if the request path or headers indicate JWT authentication
        return true;
    }
    use TargetPathTrait;

    private JWTTokenManagerInterface $jwtManager;
    private LoggerInterface $logger;
    private UserProviderInterface $userProvider;

    

    public function __construct(JWTTokenManagerInterface $jwtManager, LoggerInterface $logger, UserProviderInterface $userProvider, private UrlGeneratorInterface $urlGenerator, private EntityManagerInterface $entityManager)
    {
        $this->jwtManager = $jwtManager;
        $this->logger = $logger;
        $this->userProvider = $userProvider;
    }

    public function authenticate(Request $request): Passport
    {
        dd('sdfsdfsfs');
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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        dd('sdfdsf');
        // /** @var UserInterface $user */
        // $user = $token->getUser();
        // $jwt = $this->jwtManager->create($user);

        // $response = new JWTAuthenticationSuccessResponse($jwt);

        // if (in_array('ROLE_ADMIN', $user->getRoles())) {
        //     $response->headers->set('Location', $this->urlGenerator->generate('admin_homepage'));
        // } else {
        //     $response->headers->set('Location', $this->urlGenerator->generate('user_homepage'));
        // }
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($targetPath);

    }
    private function invalidCredentialsMessage()
    {
        throw new CustomUserMessageAuthenticationException('Invalid credentials, please try again, click Forgot Password? or email ' . $supportEmail['supportEmail'] . ' for assistance.');
    }
}