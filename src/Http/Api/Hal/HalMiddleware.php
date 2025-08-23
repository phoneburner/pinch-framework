<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use PhoneBurner\ApiHandler\TransformableResource;
use PhoneBurner\ApiHandler\TransformableResponse;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HalMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! $response instanceof TransformableResponse) {
            return $response;
        }

        $match = $request->getAttribute(RouteMatch::class);
        if (! $match instanceof RouteMatch) {
            return $response;
        }

        $attributes = $match->getAttributes();
        if (! isset($attributes[Linker::class], $attributes[Embedder::class])) {
            return $response;
        }

        $linker = $this->container->get($attributes[Linker::class]);
        \assert($linker instanceof Linker);

        $embedder = $this->container->get($attributes[Embedder::class]);
        \assert($embedder instanceof Embedder);

        $transformer = new HalTransformer(
            $response->transformable_resource->transformer,
            $linker,
            $embedder,
        );

        return $response->withTransformableResource(
            new TransformableResource(
                $response->transformable_resource->resource,
                $response->transformable_resource->request,
                $transformer,
            ),
        );
    }
}
