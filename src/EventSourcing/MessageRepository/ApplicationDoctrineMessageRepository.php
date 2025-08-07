<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing\MessageRepository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\OffsetCursor;
use EventSauce\EventSourcing\PaginationCursor;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\UnableToPersistMessages;
use EventSauce\EventSourcing\UnableToRetrieveMessages;
use EventSauce\IdEncoding\BinaryUuidIdEncoder;
use EventSauce\IdEncoding\IdEncoder;
use PhoneBurner\Pinch\Uuid\Uuid;

class DoctrineMysqlMessageRepository implements MessageRepository
{
    private const int CHUNK_SIZE = 250;

    private const string INSERT_SQL_FORMAT = <<<SQL
        INSERT INTO `%s` (`event_id`, `aggregate_root_id`, `version`, `payload`) VALUES\n%s
        SQL;

    private const string INSERT_VALUES_SQL_FORMAT = <<<'EOL'
        (:event_id_%1$d, :aggregate_root_id_%1$d, :version_%1$d, :payload_%1$d)
        EOL;

    private const string SELECT_FORMAT = <<<'SQL'
        SELECT `version`, `payload`
        FROM %s
        WHERE `aggregate_root_id` = :aggregate_root_id
            AND `version` > :aggregate_root_version
        ORDER BY `version`
        LIMIT %d
        SQL;

    private const string PAGINATION_SELECT_QUERY = <<<SQL
        SELECT `id`, `payload`
        FROM `%s`
        WHERE `id` > :id
        ORDER BY `id`
        LIMIT %d
        SQL;

    private Statement|null $pagination_statement = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table_name,
        private readonly MessageSerializer $serializer,
        private readonly int $json_encode_options = \JSON_THROW_ON_ERROR,
        private readonly IdEncoder $aggregate_root_id_encoder = new BinaryUuidIdEncoder(),
        private readonly IdEncoder $event_id_encoder = new BinaryUuidIdEncoder(),
    ) {
    }

    public function persist(Message ...$messages): void
    {
        if (! $messages) {
            return;
        }

        $insert_values = [];
        $insert_parameters = [];
        foreach ($messages as $index => $message) {
            $insert_values[] = \sprintf(
                self::INSERT_VALUES_SQL_FORMAT,
                $index,
            );
            $payload = $this->serializer->serializeMessage($message);
            $insert_parameters['event_id_' . $index] = $this->event_id_encoder->encodeId(
                $payload['headers'][Header::EVENT_ID] ??= Uuid::ordered()->toString(),
            );
            $insert_parameters['aggregate_root_id_' . $index] = $this->aggregate_root_id_encoder->encodeId(
                $message->aggregateRootId() ?? throw new \LogicException('Aggregate root ID cannot be null'),
            );
            $insert_parameters['version_' . $index] = $payload['headers'][Header::AGGREGATE_ROOT_VERSION] ?? 0;
            $insert_parameters['payload_' . $index] = \json_encode($payload, $this->json_encode_options);
        }

        try {
            $this->connection->executeStatement(
                \sprintf(self::INSERT_SQL_FORMAT, $this->table_name, \implode(",\n ", $insert_values)),
                $insert_parameters,
            );
        } catch (\Throwable $exception) {
            throw UnableToPersistMessages::dueTo('', $exception);
        }
    }

    public function retrieveAll(AggregateRootId $id): \Generator
    {
        return $this->retrieveAllAfterVersion($id, 0);
    }

    /**
     * @return \Generator<Message>
     */
    public function retrieveAllAfterVersion(AggregateRootId $id, int $aggregateRootVersion): \Generator
    {
        $statement = $this->connection->prepare(\sprintf(self::SELECT_FORMAT, $this->table_name, self::CHUNK_SIZE));
        $statement->bindValue('aggregate_root_id', $this->aggregate_root_id_encoder->encodeId($id));
        $aggregate_root_version = $aggregateRootVersion >= 0
            ? $aggregateRootVersion
            : throw new \UnexpectedValueException('Invalid Aggregate Root Version');

        try {
            do {
                $message_count = 0;
                $statement->bindValue('aggregate_root_version', $aggregate_root_version);
                $result = $statement->executeQuery();
                while ($row = $result->fetchNumeric()) {
                    ++$message_count;
                    $aggregate_root_version = (int)$row[0];
                    yield $this->serializer->unserializePayload(\json_decode((string)$row[1], true));
                }
                $result->free();
            } while ($message_count === self::CHUNK_SIZE);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo('', $exception);
        }

        return $aggregate_root_version;
    }

    public function paginate(PaginationCursor $cursor): \Generator
    {
        \assert($cursor instanceof OffsetCursor, new \LogicException(
            \sprintf('Wrong cursor type used, expected %s, received %s', OffsetCursor::class, $cursor::class),
        ));

        $this->pagination_statement ??= $this->connection->prepare(\sprintf(
            self::PAGINATION_SELECT_QUERY,
            $this->table_name,
            $cursor->limit(),
        ));

        try {
            $offset = $cursor->offset();
            $this->pagination_statement->bindValue('id', $offset);
            $result = $this->pagination_statement->executeQuery();
            while ($row = $result->fetchNumeric()) {
                $offset = $row[0];
                yield $this->serializer->unserializePayload(\json_decode((string)$row[1], true));
            }
            $result->free();
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMessages::dueTo($exception->getMessage(), $exception);
        }

        return $cursor->withOffset($offset);
    }
}
