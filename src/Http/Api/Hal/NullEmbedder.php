<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use Psr\Http\Message\ServerRequestInterface;

final class NullEmbedder implements Embedder
{
    #[\Override]
    public function embed(object $object, ServerRequestInterface $request): iterable
    {
        return [];
    }
}
