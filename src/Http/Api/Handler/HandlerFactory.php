<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Handler;

use PhoneBurner\ApiHandler\CreateHandler;
use PhoneBurner\ApiHandler\DeleteHandler;
use PhoneBurner\ApiHandler\Hydrator;
use PhoneBurner\ApiHandler\ReadHandler;
use PhoneBurner\ApiHandler\Resolver;
use PhoneBurner\ApiHandler\ResponseFactory;
use PhoneBurner\ApiHandler\Transformer;
use PhoneBurner\ApiHandler\UpdateHandler;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\ExceptionalResponseRequestHandlerDecorator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This should likely be part of API handler (or a separate package for rest)
 * and not in the application code, so not testing this here.
 *
 * @template T of object
 * @codeCoverageIgnore
 */class HandlerFactory implements \PhoneBurner\ApiHandler\HandlerFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ResponseFactory $response_factory,
    ) {
    }

    #[\Override]
    public function makeForRequest(ServerRequestInterface $request): RequestHandlerInterface
    {
        $attributes = $this->getAttributes($request);

        $handler = match (HttpMethod::tryFrom($request->getMethod())) {
            HttpMethod::Get => new ReadHandler(
                resolver: $this->getResolver($attributes),
                transformer: $this->getTransformer($attributes),
            ),
            HttpMethod::Post => new CreateHandler(
                hydrator: $this->getHydrator($attributes),
                transformer: $this->getTransformer($attributes),
            ),
            HttpMethod::Put => new UpdateHandler(
                resolver: $this->getResolver($attributes),
                hydrator: $this->getHydrator($attributes),
                transformer: $this->getTransformer($attributes),
            ),
            HttpMethod::Delete => new DeleteHandler(
                resolver: $this->getResolver($attributes),
                hydrator: $this->getHydrator($attributes),
                transformer: $this->getTransformer($attributes),
            ),
            default => throw new \RuntimeException('Unsupported request method.'),
        };

        $handler->setResponseFactory($this->response_factory);

        return new ExceptionalResponseRequestHandlerDecorator($handler);
    }

    #[\Override]
    public function canHandle(ServerRequestInterface $request): bool
    {
        $attributes = $this->getAttributes($request);

        return match (HttpMethod::tryFrom($request->getMethod())) {
            HttpMethod::Get => isset($attributes[Resolver::class], $attributes[Transformer::class]),
            HttpMethod::Post => isset($attributes[Hydrator::class], $attributes[Transformer::class]),
            HttpMethod::Put, HttpMethod::Delete => isset($attributes[Resolver::class], $attributes[Hydrator::class], $attributes[Transformer::class]),
            default => false,
        };
    }

    private function getAttributes(ServerRequestInterface $request): array
    {
        return $request->getAttribute(RouteMatch::class)?->getAttributes() ?: [];
    }

    /**
     * @return Resolver<T>
     */
    private function getResolver(array $attributes): Resolver
    {
        $resolver = $this->container->get(
            $attributes[Resolver::class] ?? throw new \LogicException('resolver not found in request attributes'),
        );

        return $resolver instanceof Resolver ? $resolver : throw new \LogicException('resolver not defined in container');
    }

    private function getTransformer(array $attributes): Transformer
    {
        $transformer = $this->container->get(
            $attributes[Transformer::class] ?? throw new \LogicException('transformer not found in request attributes'),
        );

        return $transformer instanceof Transformer ? $transformer : throw new \LogicException('transformer not defined in container');
    }

    /**
     * @return Hydrator<T>
     */
    private function getHydrator(array $attributes): Hydrator
    {
        $hydrator = $this->container->get(
            $attributes[Hydrator::class] ?? throw new \LogicException('hydrator not found in request attributes'),
        );

        return $hydrator instanceof Hydrator ? $hydrator : throw new \LogicException('hydrator not defined in container');
    }
}
