<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Configuration;

use PhoneBurner\Pinch\Component\HttpClient\Webhook\Configuration\WebhookConfigurationCollection;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleEventWebhookConfiguration;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\SimpleWebhookConfigurationCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SimpleWebhookConfigurationCollectionTest extends TestCase
{
    #[Test]
    public function implementsWebhookConfigurationCollectionInterface(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        self::assertInstanceOf(WebhookConfigurationCollection::class, $collection);
    }

    #[Test]
    public function implementsCountableInterface(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        self::assertInstanceOf(\Countable::class, $collection);
    }

    #[Test]
    public function implementsIteratorAggregateInterface(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        self::assertInstanceOf(\IteratorAggregate::class, $collection);
    }

    #[Test]
    public function constructorWithNoWebhooksCreatesEmptyCollection(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        self::assertEmpty($collection->webhooks);
        self::assertCount(0, $collection);
    }

    #[Test]
    public function constructorWithSingleWebhook(): void
    {
        $webhook = new SimpleEventWebhookConfiguration('https://example.com/webhook');
        $collection = new SimpleWebhookConfigurationCollection($webhook);

        self::assertSame([$webhook], $collection->webhooks);
        self::assertCount(1, $collection);
    }

    #[Test]
    public function constructorWithMultipleWebhooks(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook1');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook2');
        $webhook3 = new SimpleEventWebhookConfiguration('https://example.com/webhook3');

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2, $webhook3);

        self::assertSame([$webhook1, $webhook2, $webhook3], $collection->webhooks);
        self::assertCount(3, $collection);
    }

    #[Test]
    public function countReturnsCorrectNumberOfWebhooks(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook1');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook2');

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2);

        self::assertCount(2, $collection);
        self::assertCount(2, $collection);
    }

    #[Test]
    public function getIteratorYieldsAllWebhooks(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook1');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook2');
        $webhook3 = new SimpleEventWebhookConfiguration('https://example.com/webhook3');

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2, $webhook3);

        $iterator = $collection->getIterator();
        self::assertInstanceOf(\Generator::class, $iterator);

        $iterated_webhooks = [];
        foreach ($iterator as $webhook) {
            $iterated_webhooks[] = $webhook;
        }

        self::assertSame([$webhook1, $webhook2, $webhook3], $iterated_webhooks);
    }

    #[Test]
    public function getIteratorWithEmptyCollectionYieldsNothing(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        $iterator = $collection->getIterator();
        $iterated_webhooks = [];
        foreach ($iterator as $webhook) {
            $iterated_webhooks[] = $webhook;
        }

        self::assertEmpty($iterated_webhooks);
    }

    #[Test]
    public function collectionIsIterableWithForeach(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook1');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook2');

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2);

        $iterated_webhooks = [];
        foreach ($collection as $webhook) {
            $iterated_webhooks[] = $webhook;
        }

        self::assertSame([$webhook1, $webhook2], $iterated_webhooks);
    }

    #[Test]
    #[DataProvider('provideGetWebhooksForEventTestCases')]
    public function getWebhooksForEvent(array $webhooks_config, string $event_class, array $expected_indices): void
    {
        $webhooks = [];
        foreach ($webhooks_config as $config) {
            $webhooks[] = new SimpleEventWebhookConfiguration(
                uri: $config['uri'],
                events: $config['events'], // @phpstan-ignore argument.type
            );
        }

        $collection = new SimpleWebhookConfigurationCollection(...$webhooks);
        /** @phpstan-ignore argument.type */
        $result = $collection->getWebhooksForEvent($event_class);

        $expected_webhooks = [];
        foreach ($expected_indices as $index) {
            $expected_webhooks[] = $webhooks[$index];
        }

        // array_filter preserves keys, so we need to compare values only
        self::assertSame($expected_webhooks, \array_values($result));
    }

    public static function provideGetWebhooksForEventTestCases(): \Generator
    {
        yield 'no webhooks returns empty array' => [
            [],
            'App\\Events\\UserCreated',
            [],
        ];

        yield 'single matching webhook' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['App\\Events\\UserCreated']],
            ],
            'App\\Events\\UserCreated',
            [0],
        ];

        yield 'single non-matching webhook' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['App\\Events\\UserUpdated']],
            ],
            'App\\Events\\UserCreated',
            [],
        ];

        yield 'multiple webhooks with some matching' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['App\\Events\\UserCreated']],
                ['uri' => 'https://example.com/webhook2', 'events' => ['App\\Events\\UserUpdated']],
                ['uri' => 'https://example.com/webhook3', 'events' => ['App\\Events\\UserCreated', 'App\\Events\\UserDeleted']],
            ],
            'App\\Events\\UserCreated',
            [0, 2],
        ];

        yield 'wildcard webhook matches any event' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['*']],
                ['uri' => 'https://example.com/webhook2', 'events' => ['App\\Events\\UserUpdated']],
            ],
            'App\\Events\\SomeRandomEvent',
            [0],
        ];

        yield 'multiple wildcards and specific events' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['*']],
                ['uri' => 'https://example.com/webhook2', 'events' => ['App\\Events\\UserCreated']],
                ['uri' => 'https://example.com/webhook3', 'events' => ['*']],
                ['uri' => 'https://example.com/webhook4', 'events' => ['App\\Events\\UserUpdated']],
            ],
            'App\\Events\\UserCreated',
            [0, 1, 2],
        ];

        yield 'empty events arrays match nothing' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => []],
                ['uri' => 'https://example.com/webhook2', 'events' => []],
            ],
            'App\\Events\\UserCreated',
            [],
        ];

        yield 'mixed empty and populated events arrays' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => []],
                ['uri' => 'https://example.com/webhook2', 'events' => ['App\\Events\\UserCreated']],
                ['uri' => 'https://example.com/webhook3', 'events' => []],
            ],
            'App\\Events\\UserCreated',
            [1],
        ];

        yield 'case sensitive event matching' => [
            [
                ['uri' => 'https://example.com/webhook1', 'events' => ['App\\Events\\UserCreated']],
                ['uri' => 'https://example.com/webhook2', 'events' => ['app\\events\\usercreated']],
            ],
            'App\\Events\\UserCreated',
            [0],
        ];
    }

    #[Test]
    public function getWebhooksForEventWithEmptyCollectionReturnsEmptyArray(): void
    {
        $collection = new SimpleWebhookConfigurationCollection();

        /** @phpstan-ignore argument.type */
        $result = $collection->getWebhooksForEvent('App\\Events\\UserCreated');

        self::assertEmpty($result);
    }

    #[Test]
    public function getWebhooksForEventReturnsFilteredArray(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook1',
            events: ['App\\Events\\UserCreated'], // @phpstan-ignore argument.type
        );
        $webhook2 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook2',
            events: ['App\\Events\\UserDeleted'], // @phpstan-ignore argument.type
        );

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2);
        /** @phpstan-ignore argument.type */
        $result = $collection->getWebhooksForEvent('App\\Events\\UserCreated');

        // Should return only matching webhooks, not the original array
        self::assertSame([$webhook1], \array_values($result));
        self::assertNotSame($collection->webhooks, $result);
    }

    #[Test]
    public function getWebhooksForEventPreservesOriginalOrder(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook1',
            events: ['App\\Events\\UserCreated'], // @phpstan-ignore argument.type
        );
        $webhook2 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook2',
            events: ['App\\Events\\UserUpdated'], // @phpstan-ignore argument.type
        );
        $webhook3 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook3',
            events: ['App\\Events\\UserCreated'], // @phpstan-ignore argument.type
        );
        $webhook4 = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/webhook4',
            events: ['App\\Events\\UserCreated'], // @phpstan-ignore argument.type
        );

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2, $webhook3, $webhook4);
        /** @phpstan-ignore argument.type */
        $result = $collection->getWebhooksForEvent('App\\Events\\UserCreated');

        // Should return webhooks in the same order they were added, but only matching ones
        // array_filter preserves keys, so we need to compare values only
        self::assertSame([$webhook1, $webhook3, $webhook4], \array_values($result));
    }

    #[Test]
    public function collectionIsReadonly(): void
    {
        $webhook = new SimpleEventWebhookConfiguration('https://example.com/webhook');
        $collection = new SimpleWebhookConfigurationCollection($webhook);

        // The collection should be readonly, so we can't modify the webhooks property
        // This is enforced by the readonly class modifier
        self::assertSame([$webhook], $collection->webhooks);

        // Attempting to modify would result in a compile error:
        // $collection->webhooks = []; // Error: Cannot modify readonly property
    }

    #[Test]
    public function collectionWithDuplicateWebhooksKeepsAll(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook'); // Same URL

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2);

        // Both webhooks should be kept even if they have the same configuration
        self::assertCount(2, $collection);
        self::assertSame([$webhook1, $webhook2], $collection->webhooks);
        self::assertNotSame($webhook1, $webhook2); // Different instances
    }

    #[Test]
    public function getWebhooksForEventWithComplexEventFiltering(): void
    {
        $webhook_all = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/all',
            events: ['*'], // @phpstan-ignore argument.type
        );
        $webhook_user_events = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/user',
            events: ['App\\Events\\UserCreated', 'App\\Events\\UserUpdated', 'App\\Events\\UserDeleted'], // @phpstan-ignore argument.type
        );
        $webhook_specific = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/created',
            events: ['App\\Events\\UserCreated'], // @phpstan-ignore argument.type
        );
        $webhook_none = new SimpleEventWebhookConfiguration(
            uri: 'https://example.com/none',
            events: [],
        );

        $collection = new SimpleWebhookConfigurationCollection(
            $webhook_all,
            $webhook_user_events,
            $webhook_specific,
            $webhook_none,
        );

        // Test UserCreated event
        /** @phpstan-ignore argument.type */
        $user_created_webhooks = $collection->getWebhooksForEvent('App\\Events\\UserCreated');
        self::assertSame([$webhook_all, $webhook_user_events, $webhook_specific], $user_created_webhooks);

        // Test UserUpdated event
        /** @phpstan-ignore argument.type */
        $user_updated_webhooks = $collection->getWebhooksForEvent('App\\Events\\UserUpdated');
        self::assertSame([$webhook_all, $webhook_user_events], $user_updated_webhooks);

        // Test unrelated event
        /** @phpstan-ignore argument.type */
        $order_created_webhooks = $collection->getWebhooksForEvent('App\\Events\\OrderCreated');
        self::assertSame([$webhook_all], $order_created_webhooks);
    }

    #[Test]
    public function iteratorSupportsArrayFunctions(): void
    {
        $webhook1 = new SimpleEventWebhookConfiguration('https://example.com/webhook1');
        $webhook2 = new SimpleEventWebhookConfiguration('https://example.com/webhook2');
        $webhook3 = new SimpleEventWebhookConfiguration('https://example.com/webhook3');

        $collection = new SimpleWebhookConfigurationCollection($webhook1, $webhook2, $webhook3);

        // Convert to array using iterator_to_array
        $array = \iterator_to_array($collection);
        self::assertSame([$webhook1, $webhook2, $webhook3], $array);

        // Use array functions with iterator
        $urls = [];
        foreach ($collection as $webhook) {
            $urls[] = $webhook->uri;
        }
        self::assertSame(['https://example.com/webhook1', 'https://example.com/webhook2', 'https://example.com/webhook3'], $urls);
    }
}
