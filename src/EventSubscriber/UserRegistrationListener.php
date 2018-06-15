<?php

namespace App\EventSubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\AuthenticationSuccessHandlerInterface;


use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserRegistrationListener implements EventSubscriber
{
    private $_jwtManager;
    private $_authenticationSuccessHandler;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function __construct(JWTTokenManagerInterface $jwtManager, EventDispatcherInterface $dispatcher)
    {
        $this->_jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
    }

    public function getSubscribedEvents()
    {
        return ['postPersist', 'postUpdate'];
    }


    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof User) {
            return;
        }

        $jwt = $this->_jwtManager->create($entity);
        $this->handleAuthenticationSuccess($entity, $jwt);
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof User) {
            return;
        }

        return new JsonResponse(
            [
                'token' => $this->_jwtManager->create($entity),
                'user' => $entity
            ]
        );
    }

    public function handleAuthenticationSuccess(UserInterface $user, $jwt = null)
    {
        if (null === $jwt) {
            $jwt = $this->jwtManager->create($user);
        }

        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch(Events::AUTHENTICATION_SUCCESS, $event);
        $response->setData($event->getData());

        return $response;
    }
}