<?php declare(strict_types=1);

namespace KaroIO\MessengerMonitorBundle\Controller;

use KaroIO\MessengerMonitorBundle\FailedMessage\FailedMessageRejecter;
use KaroIO\MessengerMonitorBundle\FailedMessage\FailedMessageRetryer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RejectFailedMessage
{
    private $failedMessageRejecter;
    private $session;
    private $urlGenerator;

    public function __construct(FailedMessageRejecter $failedMessageRejecter, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        $this->failedMessageRejecter = $failedMessageRejecter;
        $this->session = $session;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke($id): RedirectResponse
    {
        try {
            $this->failedMessageRejecter->rejectFailedMessage($id);
            $this->session->getBag('flashes')->add('success', sprintf('Message with id "%s" correctly rejected.', $id));
        } catch (\Exception $exception) {
            $this->session->getBag('flashes')->add('danger', sprintf('Error while rejecting message with id "%s": %s', $id, $exception->getMessage()));
        }

        return new RedirectResponse($this->urlGenerator->generate('karo-io.dashboard'));
    }
}
