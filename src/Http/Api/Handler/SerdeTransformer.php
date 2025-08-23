<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Handler;

use Crell\Serde\Serde;
use PhoneBurner\ApiHandler\Transformer;
use Psr\Http\Message\ServerRequestInterface;

class SerdeTransformer implements Transformer
{
    public function __construct(
        private readonly Serde $serde,
    ) {
    }

    #[\Override]
    public function transform(object $object, ServerRequestInterface $request): mixed
    {
        return $this->serde->serialize($object, format: 'array', scopes: ['api']);
    }
}
