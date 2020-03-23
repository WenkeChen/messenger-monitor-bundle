<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
final class NoFailureTransportController
{
    private $session;
    private $urlGenerator;

    public function __construct(SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        $this->session = $session;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(int $id): RedirectResponse
    {
        /** @var FlashBagInterface $sessionBag */
        $sessionBag = $this->session->getBag('flashes');

        $sessionBag->add('messenger_monitor.error', 'Action impossible: a failure transport should be configured first.');

        return new RedirectResponse($this->urlGenerator->generate('symfonycasts.messenger_monitor.dashboard'));
    }
}
