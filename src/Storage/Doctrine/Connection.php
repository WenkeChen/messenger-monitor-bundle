<?php

declare(strict_types=1);

namespace SymfonyCasts\MessengerMonitorBundle\Storage\Doctrine;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use Doctrine\DBAL\Types\Types;
use SymfonyCasts\MessengerMonitorBundle\Statistics\MetricsPerMessageType;
use SymfonyCasts\MessengerMonitorBundle\Statistics\Statistics;

/**
 * @internal
 * @final
 */
class Connection
{
    private $driverConnection;
    private $tableName;
    /** @var SingleDatabaseSynchronizer|null */
    private $schemaSynchronizer;

    public function __construct(DBALConnection $driverConnection, string $tableName)
    {
        $this->driverConnection = $driverConnection;
        $this->tableName = $tableName;
    }

    public function saveMessage(StoredMessage $storedMessage): void
    {
        $this->executeQuery(
            $this->driverConnection->createQueryBuilder()
                ->insert($this->tableName)
                ->values(
                    [
                        'message_uid' => ':message_uid',
                        'class' => ':class',
                        'dispatched_at' => ':dispatched_at',
                    ]
                )
                ->getSQL(),
            [
                'message_uid' => $storedMessage->getMessageUid(),
                'class' => $storedMessage->getMessageClass(),
                'dispatched_at' => (float) $storedMessage->getDispatchedAt()->format('U.u'),
            ]
        );

        $storedMessage->setId((int) $this->driverConnection->lastInsertId());
    }

    public function updateMessage(StoredMessage $storedMessage): void
    {
        $this->executeQuery(
            $this->driverConnection->createQueryBuilder()
                ->update($this->tableName)
                ->set('waiting_time', ':waiting_time')
                ->set('receiver_name', ':receiver_name')
                ->set('handling_time', ':handling_time')
                ->set('failing_time', ':failing_time')
                ->where('id = :id')
                ->getSQL(),
            [
                'waiting_time' => $storedMessage->getWaitingTime(),
                'receiver_name' => $storedMessage->getReceiverName(),
                'handling_time' => $storedMessage->getHandlingTime(),
                'failing_time' => $storedMessage->getFailingTime(),
                'id' => $storedMessage->getId(),
            ]
        );
    }

    public function findMessage(string $messageUid): ?StoredMessage
    {
        $statement = $this->executeQuery(
            $this->driverConnection->createQueryBuilder()
                ->select('*')
                ->from($this->tableName)
                ->where('message_uid = :message_uid')
                ->orderBy('dispatched_at', 'desc')
                ->setMaxResults(1)
                ->getSQL(),
            ['message_uid' => $messageUid]
        );

        if (false === $row = $statement->fetch()) {
            return null;
        }

        return new StoredMessage(
            $row['message_uid'],
            $row['class'],
            \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $row['dispatched_at'])),
            (int) $row['id'],
            null !== $row['waiting_time'] ? (float) $row['waiting_time'] : null,
            $row['receiver_name'] ?? null,
            null !== $row['handling_time'] ? (float) $row['handling_time'] : null,
            null !== $row['failing_time'] ? (float) $row['failing_time'] : null
        );
    }

    public function getStatistics(\DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): Statistics
    {
        $statement = $this->executeQuery(
            $this->driverConnection->createQueryBuilder()
                ->select('count(id) as countMessagesOnPeriod, class')
                ->addSelect('AVG(waiting_time) AS averageWaitingTime')
                ->addSelect('AVG(handling_time) AS averageHandlingTime')
                ->from($this->tableName)
                ->where('dispatched_at >= :from_date')
                ->andWhere('dispatched_at <= :to_date')
                ->groupBy('class')
                ->getSQL(),
            [
                'from_date' => (float) $fromDate->format('U'),
                'to_date' => (float) $toDate->format('U')
            ]
        );

        $statistics = new Statistics($fromDate, $toDate);
        while (false !== ($row = $statement->fetch(FetchMode::ASSOCIATIVE))) {
            $statistics->add(
                new MetricsPerMessageType(
                    $fromDate,
                    $toDate,
                    $row['class'],
                    (int) $row['countMessagesOnPeriod'],
                    $row['averageWaitingTime'] ? (float) $row['averageWaitingTime'] : null,
                    $row['averageHandlingTime'] ? (float) $row['averageHandlingTime'] : null
                )
            );
        }

        return $statistics;
    }

    private function executeQuery(string $sql, array $parameters = [], array $types = []): ResultStatement
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (TableNotFoundException $e) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            $this->setup();

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function setup(): void
    {
        $this->getSchemaSynchronizer()->updateSchema($this->getSchema(), true);
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', Types::INTEGER)->setNotnull(true)->setAutoincrement(true);
        $table->addColumn('message_uid', Types::GUID)->setNotnull(true);
        $table->addColumn('class', Types::STRING)->setLength(255)->setNotnull(true);
        $table->addColumn('dispatched_at', Types::FLOAT)->setNotnull(true);
        $table->addColumn('waiting_time', Types::FLOAT)->setNotnull(false);
        $table->addColumn('handling_time', Types::FLOAT)->setNotnull(false);
        $table->addColumn('failing_time', Types::FLOAT)->setNotnull(false);
        $table->addColumn('receiver_name', Types::STRING)->setLength(255)->setNotnull(false);
        $table->addIndex(['dispatched_at']);
        $table->addIndex(['class']);
        $table->setPrimaryKey(['id']);

        return $schema;
    }

    private function getSchemaSynchronizer(): SingleDatabaseSynchronizer
    {
        if (null === $this->schemaSynchronizer) {
            $this->schemaSynchronizer = new SingleDatabaseSynchronizer($this->driverConnection);
        }

        return $this->schemaSynchronizer;
    }
}
