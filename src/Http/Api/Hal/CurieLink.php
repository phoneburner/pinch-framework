<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use PhoneBurner\LinkTortilla\Link;
use PhoneBurner\LinkTortilla\LinkWrapper;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\HalLinkAttribute;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\StandardRel;
use Psr\Link\LinkInterface;

class CurieLink implements LinkInterface
{
    use LinkWrapper;

    public bool $is_array = true;

    public function __construct(
        public readonly string $prefix = 'docs',
        public readonly string $href = '/docs#tag/{rel}',
    ) {
        $this->setWrapped(Link::make(StandardRel::CURIES, $href)
            ->withAttribute(HalLinkAttribute::NAME, $prefix)
            ->asArray());
    }
}
