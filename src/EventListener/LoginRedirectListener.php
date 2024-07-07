<?php
// src/EventListener/LoginRedirectListener.php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class LoginRedirectListener
{
    private $security;
    private $urlGenerator;

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        // Check if the request is for the login page
        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');
        dd('sdf');
        if ($routeName === 'app_login') {
            // Check if user is already authenticated
            if ($this->security->isGranted('ROLE_USER')) {
                // Redirect to the home page
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_home')));
            }
        }
    }
}
