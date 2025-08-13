<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use PhoneBurner\Pinch\Framework\EventSourcing\Attribute\AggregateRootMetadata;

/**
 * Mock AggregateRoot with metadata attribute
 *
 * @implements AggregateRoot<MockAggregateRootId>
 */
#[AggregateRootMetadata(
    id: MockAggregateRootId::class,
    repository: MockAggregateRootRepository::class,
    table: 'mock_events',
)]
class MockAggregateRootWithMetadata implements AggregateRoot
{
    public function aggregateRootId(): AggregateRootId
    {
        return new MockAggregateRootId('test-id');
    }

    public function aggregateRootVersion(): int
    {
        return 1;
    }

    public static function reconstituteFromEvents(AggregateRootId $aggregate_root_id, object ...$events): static
    {
        return new self(); // @phpstan-ignore return.type
    }

    public function releaseEvents(): array
    {
        return [];
    }
}
