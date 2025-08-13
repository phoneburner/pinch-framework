<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\EventListener\Fixtures;

use PhoneBurner\Pinch\Component\Http\ResponseAware;
use Psr\Http\Message\ResponseInterface;

final class MockResponseAwareEvent implements ResponseAware
{
    public function __construct(
        public ResponseInterface $response,
    ) {
    }
}
