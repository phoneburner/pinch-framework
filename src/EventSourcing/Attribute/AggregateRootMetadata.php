<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing\Attribute;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use PhoneBurner\Pinch\Framework\EventSourcing\AggregateRootRepository;

use function PhoneBurner\Pinch\Attribute\attr_first;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AggregateRootMetadata
{
    /**
     * @template T1 of AggregateRootId
     * @template T2 of AggregateRootRepository
     * @param class-string<T1> $id
     * @param class-string<T2> $repository
     * @param non-empty-string $table
     */
    public function __construct(
        public string $id,
        public string $repository,
        public string $table,
    ) {
    }

    /**
     * @template T of AggregateRoot
     * @param T|class-string<T> $aggregate_root
     */
    public static function lookup(AggregateRoot|string $aggregate_root): self
    {
        return attr_first($aggregate_root, self::class) ?? throw new \LogicException(\sprintf(
            'Aggregate root %s does not have the %s attribute',
            \is_string($aggregate_root) ? $aggregate_root : $aggregate_root::class,
            self::class,
        ));
    }
}
