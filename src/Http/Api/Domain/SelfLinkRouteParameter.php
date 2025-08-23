<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Domain;

#[\Attribute]
readonly class SelfLinkRouteParameter
{
    public function __construct(
        public string $name = '',
        public string $format = '', // Optional format for datetime instances
    ) {
    }
}
