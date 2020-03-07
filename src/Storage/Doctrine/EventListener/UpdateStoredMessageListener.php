<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use SymfonyCasts\MessengerMonitorBundle\Stamp\MonitorIdStamp;
use SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\Connection;
use SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\StoredMessage;

/**
 * @internal
 */
final class UpdateStoredMessageListener implements EventSubscriberInterface
{
    private $doctrineConnection;
    private $logger;

    public function __construct(Connection $doctrineConnection, LoggerInterface $logger = null)
    {
        $this->doctrineConnection = $doctrineConnection;
        $this->logger = $logger;
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $storedMessage = $this->getStoredMessage($event->getEnvelope());

        if (null === $storedMessage) {
            return;
        }

        $storedMessage->setReceivedAt(\DateTimeImmutable::createFromFormat('U', (string) time()));
        $storedMessage->setReceiverName($event->getReceiverName());

        $this->doctrineConnection->updateMessage($storedMessage);
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $storedMessage = $this->getStoredMessage($event->getEnvelope());

        if (null === $storedMessage) {
            return;
        }

        $storedMessage->setHandledAt(\DateTimeImmutable::createFromFormat('U', (string) time()));
        $this->doctrineConnection->updateMessage($storedMessage);
    }

    private function getStoredMessage(Envelope $envelope): ?StoredMessage
    {
        /** @var MonitorIdStamp|null $monitorIdStamp */
        $monitorIdStamp = $envelope->last(MonitorIdStamp::class);

        if (null === $monitorIdStamp) {
            $this->logError('Envelope should have a MonitorIdStamp!');

            return null;
        }

        $storedMessage = $this->doctrineConnection->findMessage($monitorIdStamp->getId());

        if (null === $storedMessage) {
            $this->logError(sprintf('Message with id "%s" not found', $monitorIdStamp->getId()));

            return null;
        }

        return $storedMessage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onMessageReceived',
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];
    }

    private function logError(string $message): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->error($message);
    }
}
