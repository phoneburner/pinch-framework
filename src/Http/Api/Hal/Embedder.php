<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use Psr\Http\Message\ServerRequestInterface;

interface Embedder
{
    /**
     * @return iterable<string, HalResource|iterable<HalResource>>
     */
    public function embed(object $object, ServerRequestInterface $request): iterable;
}
