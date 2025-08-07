<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Event\HandlingLogoutRequest;
use PhoneBurner\Pinch\Component\Http\Response\RedirectResponse;
use PhoneBurner\Pinch\Component\Http\Session\SessionManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LogoutRequestHandler implements RequestHandlerInterface
{
    public const string DEFAULT_REDIRECT = '/';

    public function __construct(
        private readonly SessionManager $session_manager,
        private readonly EventDispatcherInterface $event_dispatcher,
        private readonly string $redirect = self::DEFAULT_REDIRECT,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->event_dispatcher->dispatch(new HandlingLogoutRequest($request));

        // If a session exists, destroy the data and regenerate the session ID
        if ($this->session_manager->started()) {
            $this->session_manager->invalidate();
        }

        // Redirect to login page
        return new RedirectResponse($this->redirect, HttpStatus::TEMPORARY_REDIRECT);
    }
}
