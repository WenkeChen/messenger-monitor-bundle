<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine;

use Symfony\Component\Messenger\Envelope;
use SymfonyCasts\MessengerMonitorBundle\Stamp\MonitorIdStamp;
use SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\Exception\MessengerIdStampMissingException;

/**
 * @internal
 */
final class StoredMessage
{
    private $id;
    private $messageUid;
    private $messageClass;
    private $receiverName;
    private $dispatchedAt;
    private $waitingTime;
    private $handlingTime;
    private $failedAt;

    public function __construct(string $messageUid, string $messageClass, \DateTimeImmutable $dispatchedAt, int $id = null, ?float $waitingTime = null, ?float $handlingTime = null, ?\DateTimeImmutable $failedAt = null, ?string $receiverName = null)
    {
        $this->id = $id;
        $this->messageUid = $messageUid;
        $this->messageClass = $messageClass;
        $this->dispatchedAt = $dispatchedAt;
        $this->waitingTime = $waitingTime;
        $this->handlingTime = $handlingTime;
        $this->failedAt = $failedAt;
        $this->receiverName = $receiverName;
    }

    public static function fromEnvelope(Envelope $envelope): self
    {
        /** @var MonitorIdStamp|null $monitorIdStamp */
        $monitorIdStamp = $envelope->last(MonitorIdStamp::class);

        if (null === $monitorIdStamp) {
            throw new MessengerIdStampMissingException();
        }

        return new self(
            $monitorIdStamp->getId(),
            \get_class($envelope->getMessage()),
            \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
        );
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @psalm-ignore-nullable-return
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageUid(): string
    {
        return $this->messageUid;
    }

    public function getMessageClass(): string
    {
        return $this->messageClass;
    }

    public function getDispatchedAt(): \DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getWaitingTime(): ?float
    {
        return $this->waitingTime;
    }

    public function updateWaitingTime(): void
    {
        $now = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true));
        $this->waitingTime = round((float) $now->format('U.v') - (float) $this->dispatchedAt->format('U.v'), 3);
    }

    public function getHandlingTime(): ?float
    {
        return $this->handlingTime;
    }

    public function updateHandlingTime(): void
    {
        $now = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true));
        $this->handlingTime = round((float) $now->format('U.v') - (float) $this->dispatchedAt->format('U.v') - $this->waitingTime, 3);
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(\DateTimeImmutable $failedAt): void
    {
        $this->failedAt = $failedAt;
    }

    public function setReceiverName(string $receiverName): void
    {
        $this->receiverName = $receiverName;
    }

    public function getReceiverName(): ?string
    {
        return $this->receiverName;
    }
}
