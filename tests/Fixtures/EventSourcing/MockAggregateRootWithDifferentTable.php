<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use PhoneBurner\Pinch\Framework\EventSourcing\Attribute\AggregateRootMetadata;

/**
 * Mock AggregateRoot with different table name
 *
 * @implements AggregateRoot<MockAggregateRootId>
 */
#[AggregateRootMetadata(
    id: MockAggregateRootId::class,
    repository: MockAggregateRootRepository::class,
    table: 'different_events_table',
)]
final class MockAggregateRootWithDifferentTable implements AggregateRoot
{
    public function aggregateRootId(): AggregateRootId
    {
        return new MockAggregateRootId('test-id');
    }

    public function aggregateRootVersion(): int
    {
        return 1;
    }

    public static function reconstituteFromEvents(AggregateRootId $aggregateRootId, object ...$events): static
    {
        return new static();
    }

    public function releaseEvents(): array
    {
        return [];
    }
}
