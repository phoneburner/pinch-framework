<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;

/**
 * Based on the EventSauce\EventSourcing\AggregateRootRepository interface,
 * with more specific type hints for the aggregate root parameter and return type.
 *
 * @template T of AggregateRoot
 */
interface AggregateRootRepository
{
    /**
     * @return T
     */
    public function retrieve(AggregateRootId|string $id): AggregateRoot;

    /**
     * @param T $aggregate_root
     * @return T
     */
    public function persist(AggregateRoot $aggregate_root): AggregateRoot;
}
