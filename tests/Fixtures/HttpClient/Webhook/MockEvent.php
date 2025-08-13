<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\HttpClient\Webhook;

/**
 * Mock event class for webhook testing purposes.
 */
final readonly class MockEvent
{
    public function __construct(
        public string $id = 'test-event-id',
        public string $data = 'test-data',
    ) {
    }
}
