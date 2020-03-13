<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Tests\Storage\Doctrine;

use App\Utility\Payment\Exporter\PayoutExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;
use SymfonyCasts\MessengerMonitorBundle\Stamp\MonitorIdStamp;
use SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\Exception\MessengerIdStampMissingException;
use SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine\StoredMessage;
use SymfonyCasts\MessengerMonitorBundle\Tests\TestableMessage;

final class StoredMessageTest extends TestCase
{
    public function testStoredMessage(): void
    {
        $storedMessage = new StoredMessage(
            'message_uid', TestableMessage::class, $dispatchedAt = new \DateTimeImmutable(), 1, $waitingTime = 0.1, $handlingTime = 0.2, $failedAt = new \DateTimeImmutable(), $receiverName = 'receiver_name'
        );

        $this->assertSame(1, $storedMessage->getId());
        $this->assertSame('message_uid', $storedMessage->getMessageUid());
        $this->assertSame(TestableMessage::class, $storedMessage->getMessageClass());
        $this->assertSame($dispatchedAt, $storedMessage->getDispatchedAt());
        $this->assertSame($waitingTime, $storedMessage->getWaitingTime());
        $this->assertSame($handlingTime, $storedMessage->getHandlingTime());
        $this->assertSame($failedAt, $storedMessage->getFailedAt());
        $this->assertSame($receiverName, $storedMessage->getReceiverName());
    }

    public function testCreateFromEnvelope(): void
    {
        $storedMessage = StoredMessage::fromEnvelope(
            new Envelope(new TestableMessage(), [$stamp = new MonitorIdStamp()])
        );

        $this->assertSame($stamp->getId(), $storedMessage->getMessageUid());
        $this->assertSame(TestableMessage::class, $storedMessage->getMessageClass());
    }

    public function testExceptionWhenCreateFromEnvelopeWithoutStamp(): void
    {
        $this->expectException(MessengerIdStampMissingException::class);
        $this->expectExceptionMessage('Envelope should have a MonitorIdStamp!');

        StoredMessage::fromEnvelope(new Envelope(new TestableMessage()));
    }

    public function testUpdateWaitingTime(): void
    {
        ClockMock::register(StoredMessage::class);
        ClockMock::withClockMock((new \DateTimeImmutable('2020-01-01 00:00:01.123'))->format('U.u'));

        $storedMessage = new StoredMessage('message_uid', TestableMessage::class, new \DateTimeImmutable('2020-01-01 00:00:00.000'));

        $storedMessage->updateWaitingTime();
        $this->assertSame(1.123, $storedMessage->getWaitingTime());
    }

    public function testUpdateHandlingTime(): void
    {
        ClockMock::register(StoredMessage::class);
        ClockMock::withClockMock((new \DateTimeImmutable('2020-01-01 00:00:02.123'))->format('U.u'));

        $storedMessage = new StoredMessage('message_uid', TestableMessage::class, new \DateTimeImmutable('2020-01-01 00:00:00.000'), 1, 1.0);

        $storedMessage->updateHandlingTime();
        $this->assertSame(1.123, $storedMessage->getHandlingTime());
    }
}
