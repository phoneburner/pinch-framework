<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use PhoneBurner\Pinch\Framework\EventSourcing\Attribute\AggregateRootMetadata;

/**
 * Mock child aggregate root that inherits from parent
 */
#[AggregateRootMetadata(
    id: MockAggregateRootId::class,
    repository: MockAggregateRootRepository::class,
    table: 'child_events',
)]
final class MockChildAggregateRoot extends MockAggregateRootWithMetadata
{
    // Inherits all behavior from parent
}
