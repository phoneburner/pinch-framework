<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\EventListener\Fixtures;

use PhoneBurner\Pinch\Component\Http\RequestAware;
use Psr\Http\Message\RequestInterface;

final class MockRequestAwareEvent implements RequestAware
{
    public function __construct(
        public RequestInterface $request,
    ) {
    }
}
