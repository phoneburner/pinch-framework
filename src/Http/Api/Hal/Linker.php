<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Link\LinkInterface;

interface Linker
{
    /**
     * @return array<LinkInterface>
     */
    public function links(object $object, ServerRequestInterface $request): array;
}
