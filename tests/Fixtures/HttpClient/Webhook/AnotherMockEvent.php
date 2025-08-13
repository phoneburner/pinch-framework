<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures\HttpClient\Webhook;

/**
 * Another mock event class for webhook testing purposes.
 */
final readonly class AnotherMockEvent
{
    public function __construct(
        public string $name = 'another-test-event',
        public array $metadata = [],
    ) {
    }
}
