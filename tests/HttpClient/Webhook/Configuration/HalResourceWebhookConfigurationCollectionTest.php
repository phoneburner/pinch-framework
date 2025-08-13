<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HttpClient\Webhook\Configuration;

use GuzzleHttp\Psr7\Uri;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\HalResourceWebhookConfiguration;
use PhoneBurner\Pinch\Framework\HttpClient\Webhook\Configuration\HalResourceWebhookConfigurationCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HalResourceWebhookConfigurationCollection::class)]
final class HalResourceWebhookConfigurationCollectionTest extends TestCase
{
    #[Test]
    public function constructorCreatesEmptyCollection(): void
    {
        $collection = new HalResourceWebhookConfigurationCollection();

        self::assertCount(0, $collection);
        self::assertEmpty(\iterator_to_array($collection));
    }

    #[Test]
    public function constructorAcceptsMultipleConfigurations(): void
    {
        $config1 = new HalResourceWebhookConfiguration(new Uri('https://webhook1.example.com'));
        $config2 = new HalResourceWebhookConfiguration(new Uri('https://webhook2.example.com'));
        $config3 = new HalResourceWebhookConfiguration(new Uri('https://webhook3.example.com'));

        $collection = new HalResourceWebhookConfigurationCollection($config1, $config2, $config3);

        self::assertCount(3, $collection);
        $configurations = \iterator_to_array($collection);
        self::assertSame($config1, $configurations[0]);
        self::assertSame($config2, $configurations[1]);
        self::assertSame($config3, $configurations[2]);
    }

    #[Test]
    public function constructorAcceptsSingleConfiguration(): void
    {
        $config = new HalResourceWebhookConfiguration(new Uri('https://single.example.com'));
        $collection = new HalResourceWebhookConfigurationCollection($config);

        self::assertCount(1, $collection);
        $configurations = \iterator_to_array($collection);
        self::assertSame($config, $configurations[0]);
    }

    #[Test]
    public function countReturnsCorrectNumber(): void
    {
        $collection = new HalResourceWebhookConfigurationCollection();
        self::assertCount(0, $collection);

        $config1 = new HalResourceWebhookConfiguration(new Uri('https://example1.com'));
        $collection_with_one = new HalResourceWebhookConfigurationCollection($config1);
        self::assertCount(1, $collection_with_one);

        $config2 = new HalResourceWebhookConfiguration(new Uri('https://example2.com'));
        $config3 = new HalResourceWebhookConfiguration(new Uri('https://example3.com'));
        $collection_with_multiple = new HalResourceWebhookConfigurationCollection($config1, $config2, $config3);
        self::assertCount(3, $collection_with_multiple);
    }

    #[Test]
    public function getIteratorReturnsGenerator(): void
    {
        $config1 = new HalResourceWebhookConfiguration(new Uri('https://first.example.com'));
        $config2 = new HalResourceWebhookConfiguration(new Uri('https://second.example.com'));

        $collection = new HalResourceWebhookConfigurationCollection($config1, $config2);
        $iterator = $collection->getIterator();

        self::assertInstanceOf(\Generator::class, $iterator);

        $items = \iterator_to_array($iterator);
        self::assertCount(2, $items);
        self::assertSame($config1, $items[0]);
        self::assertSame($config2, $items[1]);
    }

    #[Test]
    public function forEventReturnsMatchingConfigurations(): void
    {
        $user_webhook = new HalResourceWebhookConfiguration(
            new Uri('https://user.example.com'),
            ['UserCreated', 'UserUpdated'], // @phpstan-ignore argument.type
        );

        $order_webhook = new HalResourceWebhookConfiguration(
            new Uri('https://order.example.com'),
            ['OrderCreated', 'OrderCompleted'], // @phpstan-ignore argument.type
        );

        $wildcard_webhook = new HalResourceWebhookConfiguration(
            new Uri('https://all.example.com'),
            ['*'], // @phpstan-ignore argument.type
        );

        $collection = new HalResourceWebhookConfigurationCollection(
            $user_webhook,
            $order_webhook,
            $wildcard_webhook,
        );

        $user_created_webhooks = $collection->forEvent('UserCreated');
        self::assertCount(2, $user_created_webhooks); // user_webhook + wildcard_webhook

        $order_created_webhooks = $collection->forEvent('OrderCreated');
        self::assertCount(2, $order_created_webhooks); // order_webhook + wildcard_webhook

        $unknown_event_webhooks = $collection->forEvent('UnknownEvent');
        self::assertCount(1, $unknown_event_webhooks); // only wildcard_webhook
    }

    #[Test]
    public function forEventReturnsEmptyArrayWhenNoMatches(): void
    {
        $config = new HalResourceWebhookConfiguration(
            new Uri('https://specific.example.com'),
            ['SpecificEvent'], // @phpstan-ignore argument.type
        );

        $collection = new HalResourceWebhookConfigurationCollection($config);
        $result = $collection->forEvent('DifferentEvent');

        self::assertEmpty($result);
    }

    #[Test]
    public function forEventHandlesEmptyCollection(): void
    {
        $collection = new HalResourceWebhookConfigurationCollection();
        $result = $collection->forEvent('AnyEvent');

        self::assertEmpty($result);
    }

    #[Test]
    #[DataProvider('provideEventFilteringScenarios')]
    public function forEventFiltersCorrectly(array $webhook_configs, string $event_class, int $expected_count): void
    {
        $webhooks = [];
        foreach ($webhook_configs as $config) {
            $webhooks[] = new HalResourceWebhookConfiguration(
                new Uri($config['uri']),
                $config['events'],
            );
        }

        $collection = new HalResourceWebhookConfigurationCollection(...$webhooks);
        $result = $collection->forEvent($event_class);

        self::assertCount($expected_count, $result);
    }

    #[Test]
    public function iterationMaintainsOrder(): void
    {
        $config1 = new HalResourceWebhookConfiguration(new Uri('https://first.example.com'));
        $config2 = new HalResourceWebhookConfiguration(new Uri('https://second.example.com'));
        $config3 = new HalResourceWebhookConfiguration(new Uri('https://third.example.com'));

        $collection = new HalResourceWebhookConfigurationCollection($config1, $config2, $config3);

        $iterated_configs = [];
        foreach ($collection as $config) {
            $iterated_configs[] = $config;
        }

        self::assertSame($config1, $iterated_configs[0]);
        self::assertSame($config2, $iterated_configs[1]);
        self::assertSame($config3, $iterated_configs[2]);
    }

    #[Test]
    public function collectionIsReadonly(): void
    {
        $config = new HalResourceWebhookConfiguration(new Uri('https://example.com'));
        $collection = new HalResourceWebhookConfigurationCollection($config);

        // Collection is readonly, so we can't modify it after construction
        // This test verifies that the readonly nature is preserved
        self::assertCount(1, $collection);

        $reflection = new \ReflectionClass($collection);
        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function forEventReturnsArrayOfCorrectType(): void
    {
        $config = new HalResourceWebhookConfiguration(
            new Uri('https://typed.example.com'),
            ['TypedEvent'], // @phpstan-ignore argument.type
        );

        $collection = new HalResourceWebhookConfigurationCollection($config);
        $result = $collection->forEvent('TypedEvent');

        self::assertIsArray($result);
        self::assertContainsOnlyInstancesOf(HalResourceWebhookConfiguration::class, $result);
    }

    #[Test]
    public function forEventWithComplexEventNames(): void
    {
        $config = new HalResourceWebhookConfiguration(
            new Uri('https://complex.example.com'),
            ['App\\Events\\UserRegistered', 'Domain.Order.OrderCompleted', 'system-event-name'], // @phpstan-ignore argument.type
        );

        $collection = new HalResourceWebhookConfigurationCollection($config);

        self::assertCount(1, $collection->forEvent('App\\Events\\UserRegistered'));
        self::assertCount(1, $collection->forEvent('Domain.Order.OrderCompleted'));
        self::assertCount(1, $collection->forEvent('system-event-name'));
        self::assertCount(0, $collection->forEvent('NonExistentEvent'));
    }

    public static function provideEventFilteringScenarios(): \Generator
    {
        yield 'single webhook matches' => [
            [
                ['uri' => 'https://single.example.com', 'events' => ['TargetEvent']],
            ],
            'TargetEvent',
            1,
        ];

        yield 'multiple webhooks match' => [
            [
                ['uri' => 'https://first.example.com', 'events' => ['SharedEvent']],
                ['uri' => 'https://second.example.com', 'events' => ['SharedEvent', 'OtherEvent']],
                ['uri' => 'https://third.example.com', 'events' => ['DifferentEvent']],
            ],
            'SharedEvent',
            2,
        ];

        yield 'wildcard webhook matches' => [
            [
                ['uri' => 'https://wildcard.example.com', 'events' => ['*']],
                ['uri' => 'https://specific.example.com', 'events' => ['SpecificEvent']],
            ],
            'RandomEvent',
            1,
        ];

        yield 'no webhooks match' => [
            [
                ['uri' => 'https://nomatch.example.com', 'events' => ['DifferentEvent']],
            ],
            'UnrelatedEvent',
            0,
        ];

        yield 'all webhooks match wildcard' => [
            [
                ['uri' => 'https://all1.example.com', 'events' => ['*']],
                ['uri' => 'https://all2.example.com', 'events' => ['*']],
                ['uri' => 'https://all3.example.com', 'events' => ['*']],
            ],
            'AnyEvent',
            3,
        ];
    }
}
