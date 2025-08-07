<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing;

use EventSauce\EventSourcing\AggregateRootId;

/**
 * Mock AggregateRootId for testing
 */
final class MockAggregateRootId implements AggregateRootId
{
    public function __construct(private string $id)
    {
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        return $this->id !== '' ? $this->id : 'default-id';
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new self($aggregateRootId);
    }
}
