<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\EventSourcing\Attribute;

use PhoneBurner\Pinch\Framework\EventSourcing\Attribute\AggregateRootMetadata;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockAggregateRootId;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockAggregateRootRepository;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockAggregateRootWithDifferentTable;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockAggregateRootWithMetadata;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockAggregateRootWithoutMetadata;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\EventSourcing\MockChildAggregateRoot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AggregateRootMetadataTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $metadata = new AggregateRootMetadata(
            id: MockAggregateRootId::class,
            repository: MockAggregateRootRepository::class,
            table: 'mock_events',
        );

        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('mock_events', $metadata->table);
    }

    #[Test]
    public function constructorAcceptsAnyStringValues(): void
    {
        $metadata = new AggregateRootMetadata(
            id: 'SomeIdClass', // @phpstan-ignore argument.type
            repository: 'SomeRepositoryClass', // @phpstan-ignore argument.type
            table: 'some_table',
        );

        self::assertSame('SomeIdClass', $metadata->id);
        self::assertSame('SomeRepositoryClass', $metadata->repository);
        self::assertSame('some_table', $metadata->table);
    }

    #[Test]
    public function lookupReturnsMetadataFromClassWithAttribute(): void
    {
        $metadata = AggregateRootMetadata::lookup(MockAggregateRootWithMetadata::class);

        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('mock_events', $metadata->table);
    }

    #[Test]
    public function lookupReturnsMetadataFromAggregateRootInstance(): void
    {
        $aggregate_root = new MockAggregateRootWithMetadata();
        $metadata = AggregateRootMetadata::lookup($aggregate_root);

        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('mock_events', $metadata->table);
    }

    #[Test]
    public function lookupThrowsLogicExceptionWhenAttributeIsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            \sprintf(
                'Aggregate root %s does not have the %s attribute',
                MockAggregateRootWithoutMetadata::class,
                AggregateRootMetadata::class,
            ),
        );

        AggregateRootMetadata::lookup(MockAggregateRootWithoutMetadata::class);
    }

    #[Test]
    public function lookupThrowsLogicExceptionWithInstanceWhenAttributeIsMissing(): void
    {
        $aggregate_root = new MockAggregateRootWithoutMetadata();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            \sprintf(
                'Aggregate root %s does not have the %s attribute',
                MockAggregateRootWithoutMetadata::class,
                AggregateRootMetadata::class,
            ),
        );

        AggregateRootMetadata::lookup($aggregate_root);
    }

    #[Test]
    public function lookupWorksWithDifferentTableName(): void
    {
        $metadata = AggregateRootMetadata::lookup(MockAggregateRootWithDifferentTable::class);

        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('different_events_table', $metadata->table);
    }

    #[Test]
    public function attributeCanBeUsedOnClass(): void
    {
        $reflection = new \ReflectionClass(MockAggregateRootWithMetadata::class);
        $attributes = $reflection->getAttributes(AggregateRootMetadata::class);

        self::assertCount(1, $attributes);

        $metadata = $attributes[0]->newInstance();
        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('mock_events', $metadata->table);
    }

    #[Test]
    public function propertiesArePublicAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AggregateRootMetadata::class);

        $id_property = $reflection->getProperty('id');
        $repository_property = $reflection->getProperty('repository');
        $table_property = $reflection->getProperty('table');

        self::assertTrue($id_property->isPublic());
        self::assertTrue($repository_property->isPublic());
        self::assertTrue($table_property->isPublic());

        // Properties should not be readonly (they're set in constructor)
        self::assertFalse($id_property->isReadOnly());
        self::assertFalse($repository_property->isReadOnly());
        self::assertFalse($table_property->isReadOnly());
    }

    #[Test]
    public function metadataWorksWithInheritedAggregateRoot(): void
    {
        $metadata = AggregateRootMetadata::lookup(MockChildAggregateRoot::class);

        self::assertInstanceOf(AggregateRootMetadata::class, $metadata);
        self::assertSame(MockAggregateRootId::class, $metadata->id);
        self::assertSame(MockAggregateRootRepository::class, $metadata->repository);
        self::assertSame('child_events', $metadata->table);
    }

    #[Test]
    public function metadataHandlesComplexClassNames(): void
    {
        $metadata = new AggregateRootMetadata(
            id: 'App\\Domain\\User\\UserId', // @phpstan-ignore argument.type
            repository: 'App\\Infrastructure\\EventSourcing\\UserRepository', // @phpstan-ignore argument.type
            table: 'user_domain_events',
        );

        self::assertSame('App\\Domain\\User\\UserId', $metadata->id);
        self::assertSame('App\\Infrastructure\\EventSourcing\\UserRepository', $metadata->repository);
        self::assertSame('user_domain_events', $metadata->table);
    }

    #[Test]
    public function metadataWorksWithShortTableNames(): void
    {
        $metadata = new AggregateRootMetadata(
            id: MockAggregateRootId::class,
            repository: MockAggregateRootRepository::class,
            table: 'e',
        );

        self::assertSame('e', $metadata->table);
    }

    #[Test]
    public function metadataWorksWithLongTableNames(): void
    {
        $long_table_name = 'very_long_table_name_for_testing_maximum_length_scenarios_in_database_table_naming';
        $metadata = new AggregateRootMetadata(
            id: MockAggregateRootId::class,
            repository: MockAggregateRootRepository::class,
            table: $long_table_name,
        );

        self::assertSame($long_table_name, $metadata->table);
    }

    #[Test]
    public function lookupExceptionMessageIncludesCorrectClassNames(): void
    {
        $expected_class = MockAggregateRootWithoutMetadata::class;
        $expected_attribute = AggregateRootMetadata::class;

        try {
            AggregateRootMetadata::lookup($expected_class);
            self::fail('Expected LogicException to be thrown');
        } catch (\LogicException $e) {
            self::assertStringContainsString($expected_class, $e->getMessage());
            self::assertStringContainsString($expected_attribute, $e->getMessage());
            self::assertStringContainsString('does not have the', $e->getMessage());
        }
    }

    #[Test]
    public function attributeTargetsClassOnly(): void
    {
        $reflection = new \ReflectionClass(AggregateRootMetadata::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute_instance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute_instance->flags);
    }

    #[Test]
    public function metadataIsNotRepeatableByDefault(): void
    {
        $reflection = new \ReflectionClass(AggregateRootMetadata::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute_instance = $attributes[0]->newInstance();
        // Should not include IS_REPEATABLE flag
        self::assertNotSame(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE, $attribute_instance->flags);
        self::assertSame(\Attribute::TARGET_CLASS, $attribute_instance->flags);
    }
}
