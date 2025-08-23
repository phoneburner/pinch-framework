<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use PhoneBurner\ApiHandler\Transformer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Link\LinkInterface;

class HalTransformer implements Transformer
{
    public function __construct(
        public readonly Transformer $transformer,
        public readonly Linker $linker,
        public readonly Embedder $embedder,
        public readonly CurieLink|null $curie_link = new CurieLink(),
    ) {
    }

    #[\Override]
    public function transform(object $object, ServerRequestInterface $request): HalResource
    {
        // transform the object with the property transformer
        $content = $this->transformer->transform($object, $request);

        if (! \is_array($content)) {
            throw new \LogicException('Transformer must return an array to be used with HAL transformer');
        }

        return HalResource::make(
            $content,
            $this->injectDocsCurieLink($this->linker->links($object, $request)),
            $this->embedder->embed($object, $request),
        );
    }

    /**
     * @param array<LinkInterface> $links
     * @return array<LinkInterface>
     */
    private function injectDocsCurieLink(array $links): array
    {
        if ($this->curie_link === null) {
            return $links;
        }

        foreach ($links as $link) {
            \assert($link instanceof LinkInterface);
            if (\array_any($link->getRels(), static fn(string $rel): bool => \str_starts_with($rel, 'docs:'))) {
                return [$this->curie_link, ...$links];
            }
        }

        return $links;
    }
}
