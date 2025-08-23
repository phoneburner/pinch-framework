<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Handler;

use PhoneBurner\ApiHandler\Resolver;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\BadRequestResponse;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ResourceNotFoundResponse;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Component\Http\Routing\Router;
use PhoneBurner\Pinch\Framework\Http\Request\RequestFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @implements Resolver<object|ResponseInterface>
 */
class GenericResolver implements Resolver
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Router $router,
        private readonly array $attributes_to_retain = [],
    ) {
    }

    public function resolveFromUri(
        UriInterface $uri,
        ServerRequestInterface|null $original_request = null,
    ): object {
        $attributes = [];
        foreach ($this->attributes_to_retain as $attribute) {
            $attributes[$attribute] = $original_request?->getAttribute($attribute);
        }

        return $this->resolve(new RequestFactory()->createServerRequest(
            method: HttpMethod::Get,
            uri: $uri,
            body: $original_request?->getBody() ?? Psr7::stream(),
            attributes: $attributes,
        ));
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): object
    {
        $result = $this->router->resolveForRequest($request);
        if (! $result->isFound()) {
            return new ResourceNotFoundResponse();
        }

        $route_match = $result->getRouteMatch();
        $request = $request->withAttribute(RouteMatch::class, $route_match);

        $resolver = $route_match->getAttributes()[Resolver::class] ?? null;
        if ($resolver === null) {
            return new BadRequestResponse(detail: 'Invalid Subresource');
        }

        $resolver = $this->container->get($resolver);
        \assert($resolver instanceof Resolver);

        return $resolver->resolve($request);
    }
}
