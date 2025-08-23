<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Api\Hal;

use PhoneBurner\ApiHandler\ResponseFactory;
use PhoneBurner\ApiHandler\TransformableResource;
use PhoneBurner\ApiHandler\TransformableResponse;
use PhoneBurner\ApiHandler\Transformer;
use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\Embedder;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\HalMiddleware;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\HalTransformer;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\Linker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HalMiddlewareTest extends TestCase
{
    #[Test]
    public function handleWrapsExistingTransformIntoHalTransformer(): void
    {
        $factory = $this->createMock(ResponseFactory::class);
        $factory->expects($this->never())->method('make');

        $transformer = $this->createMock(Transformer::class);
        $object = new \stdClass();

        $linker = $this->createMock(Linker::class);
        $embedder = $this->createMock(Embedder::class);

        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([
                Linker::class => $linker::class,
                Embedder::class => $embedder::class,
            ]);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                [$linker::class, $linker],
                [$embedder::class, $embedder],
            ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with(RouteMatch::class)
            ->willReturn($route_match);

        $resource = new TransformableResource(
            $object,
            $request,
            $transformer,
        );

        $response = new TransformableResponse(
            $resource,
            $factory,
            HttpStatus::OK,
        );

        $middleware = new HalMiddleware($container);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $result = $middleware->process($request, $next);

        self::assertInstanceOf(TransformableResponse::class, $result);
        self::assertNotSame($result, $response);

        $transformable_resource = $result->transformable_resource;
        self::assertSame($object, $transformable_resource->resource);
        self::assertSame($request, $transformable_resource->request);

        $transformer = $transformable_resource->transformer;
        self::assertInstanceOf(HalTransformer::class, $transformer);
        self::assertSame($linker, $transformer->linker);
        self::assertSame($embedder, $transformer->embedder);
    }

    #[Test]
    public function handlePassesResponseIfNotTransformable(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $middleware = new HalMiddleware($container);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $result = $middleware->process($request, $next);

        self::assertSame($response, $result);
    }
}
