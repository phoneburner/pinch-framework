<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use PhoneBurner\Pinch\Framework\EventSourcing\AggregateRootRepository;

/**
 * Mock AggregateRootRepository for testing
 *
 * @implements AggregateRootRepository<MockAggregateRootWithMetadata>
 */
final class MockAggregateRootRepository implements AggregateRootRepository
{
    public function retrieve(AggregateRootId|string $id): AggregateRoot
    {
        return new MockAggregateRootWithMetadata();
    }

    public function persist(AggregateRoot $aggregate_root): AggregateRoot
    {
        return $aggregate_root;
    }
}
