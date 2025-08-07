<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;

/**
 * Mock AggregateRoot without metadata attribute
 *
 * @implements AggregateRoot<MockAggregateRootId>
 */
final class MockAggregateRootWithoutMetadata implements AggregateRoot
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
