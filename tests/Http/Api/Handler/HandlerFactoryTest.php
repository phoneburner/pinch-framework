<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Api\Handler;

use PhoneBurner\ApiHandler\CreateHandler;
use PhoneBurner\ApiHandler\DeleteHandler;
use PhoneBurner\ApiHandler\Handler;
use PhoneBurner\ApiHandler\Hydrator;
use PhoneBurner\ApiHandler\ReadHandler;
use PhoneBurner\ApiHandler\Resolver;
use PhoneBurner\ApiHandler\ResponseFactory;
use PhoneBurner\ApiHandler\Transformer;
use PhoneBurner\ApiHandler\UpdateHandler;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Framework\Http\Api\Handler\HandlerFactory;
use PhoneBurner\Pinch\Framework\Http\RequestHandler\ExceptionalResponseRequestHandlerDecorator;
use PhoneBurner\Pinch\Framework\Tests\TestSupport\MockRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HandlerFactoryTest extends TestCase
{
    use MockRequest;

    /**
     * @param class-string<Handler> $expected_handler_class
     * @throws \JsonException
     * @throws Exception
     */
    #[DataProvider('provideHttpMethodsWithHandlers')]
    #[Test]
    public function makeForRequestCreatesCorrectHandlerForMethod(
        string $method,
        string $expected_handler_class,
        array $required_attributes,
    ): void {
        $resolver = $this->createMock(Resolver::class);
        $hydrator = $this->createMock(Hydrator::class);
        $transformer = $this->createMock(Transformer::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn($required_attributes);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->atLeast(1))
            ->method('get')
            ->willReturnMap([
                ['resolver_class', $resolver],
                ['hydrator_class', $hydrator],
                ['transformer_class', $transformer],
            ]);

        $request = $this->buildMockRequest()
            ->withRequestMethod($method)
            ->withAttribute(RouteMatch::class, $route_match)
            ->make();

        $factory = new HandlerFactory($container, $response_factory);
        $result = $factory->makeForRequest($request);

        self::assertInstanceOf(ExceptionalResponseRequestHandlerDecorator::class, $result);

        // Use reflection to access the wrapped handler
        $reflection = new \ReflectionClass($result);
        $wrapped_property = $reflection->getProperty('handler');
        $wrapped_handler = $wrapped_property->getValue($result);

        self::assertInstanceOf($expected_handler_class, $wrapped_handler);
    }

    #[Test]
    public function makeForRequestThrowsForUnsupportedMethod(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('UNSUPPORTED');
        $request->method('getAttribute')
            ->with(RouteMatch::class)
            ->willReturn($route_match);

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported request method.');

        $factory->makeForRequest($request);
    }

    #[Test]
    public function makeForRequestThrowsWhenResolverMissing(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([]);

        $request = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Get->value)
            ->withAttribute(RouteMatch::class, $route_match)
            ->make();

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('resolver not found in request attributes');

        $factory->makeForRequest($request);
    }

    #[Test]
    public function makeForRequestThrowsWhenTransformerMissing(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([
                Resolver::class => 'resolver_class',
            ]);

        $request = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Get->value)
            ->withAttribute(RouteMatch::class, $route_match)
            ->make();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->atLeast(1))
            ->method('get')
            ->willReturnMap([
                ['resolver_class', $this->createMock(Resolver::class)],
                ['hydrator_class', $this->createMock(Hydrator::class)],
                ['transformer_class', $this->createMock(Transformer::class)],
            ]);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('transformer not found in request attributes');

        $factory->makeForRequest($request);
    }

    #[Test]
    public function makeForRequestThrowsWhenHydratorMissing(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([
                Resolver::class => 'resolver_class',
                Transformer::class => 'transformer_class',
            ]);

        $request = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Post->value)
            ->withAttribute(RouteMatch::class, $route_match)
            ->make();

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('hydrator not found in request attributes');

        $factory->makeForRequest($request);
    }

    #[DataProvider('provideCanHandleCases')]
    #[Test]
    public function canHandleReturnsTrueWhenRequiredAttributesPresent(
        string $method,
        array $attributes,
        bool $expected_result,
    ): void {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn($attributes);

        $request = $this->buildMockRequest()
            ->withRequestMethod($method)
            ->withAttribute(RouteMatch::class, $route_match)
            ->make();

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);
        $result = $factory->canHandle($request);

        self::assertSame($expected_result, $result);
    }

    #[Test]
    public function canHandleReturnsFalseForUnsupportedMethod(): void
    {
        $route_match = $this->createMock(RouteMatch::class);
        $route_match->expects($this->once())
            ->method('getAttributes')
            ->willReturn([]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('UNSUPPORTED');
        $request->method('getAttribute')
            ->with(RouteMatch::class)
            ->willReturn($route_match);

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);
        $result = $factory->canHandle($request);

        self::assertFalse($result);
    }

    #[Test]
    public function canHandleReturnsFalseWhenRouteMatchMissing(): void
    {
        $request = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Get->value)
            ->make();

        $container = $this->createMock(ContainerInterface::class);
        $response_factory = $this->createMock(ResponseFactory::class);

        $factory = new HandlerFactory($container, $response_factory);
        $result = $factory->canHandle($request);

        self::assertFalse($result);
    }

    public static function provideHttpMethodsWithHandlers(): \Generator
    {
        yield 'GET creates ReadHandler' => [
            HttpMethod::Get->value,
            ReadHandler::class,
            [
                Resolver::class => 'resolver_class',
                Transformer::class => 'transformer_class',
            ],
        ];

        yield 'POST creates CreateHandler' => [
            HttpMethod::Post->value,
            CreateHandler::class,
            [
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
        ];

        yield 'PUT creates UpdateHandler' => [
            HttpMethod::Put->value,
            UpdateHandler::class,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
        ];

        yield 'PATCH creates UpdateHandler' => [
            HttpMethod::Patch->value,
            UpdateHandler::class,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
        ];

        yield 'DELETE creates DeleteHandler' => [
            HttpMethod::Delete->value,
            DeleteHandler::class,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
        ];
    }

    public static function provideCanHandleCases(): \Generator
    {
        // GET cases
        yield 'GET with required attributes returns true' => [
            HttpMethod::Get->value,
            [
                Resolver::class => 'resolver_class',
                Transformer::class => 'transformer_class',
            ],
            true,
        ];

        yield 'GET without resolver returns false' => [
            HttpMethod::Get->value,
            [
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        yield 'GET without transformer returns false' => [
            HttpMethod::Get->value,
            [
                Resolver::class => 'resolver_class',
            ],
            false,
        ];

        // POST cases
        yield 'POST with required attributes returns true' => [
            HttpMethod::Post->value,
            [
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            true,
        ];

        yield 'POST without hydrator returns false' => [
            HttpMethod::Post->value,
            [
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        yield 'POST without transformer returns false' => [
            HttpMethod::Post->value,
            [
                Hydrator::class => 'hydrator_class',
            ],
            false,
        ];

        // PUT cases
        yield 'PUT with required attributes returns true' => [
            HttpMethod::Put->value,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            true,
        ];

        yield 'PUT without resolver returns false' => [
            HttpMethod::Put->value,
            [
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        yield 'PUT without hydrator returns false' => [
            HttpMethod::Put->value,
            [
                Resolver::class => 'resolver_class',
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        yield 'PUT without transformer returns false' => [
            HttpMethod::Put->value,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
            ],
            false,
        ];

        // PATCH cases
        yield 'PATCH with required attributes returns true' => [
            HttpMethod::Patch->value,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            true,
        ];

        yield 'PATCH without resolver returns false' => [
            HttpMethod::Patch->value,
            [
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        // DELETE cases
        yield 'DELETE with required attributes returns true' => [
            HttpMethod::Delete->value,
            [
                Resolver::class => 'resolver_class',
                Hydrator::class => 'hydrator_class',
                Transformer::class => 'transformer_class',
            ],
            true,
        ];

        yield 'DELETE without hydrator returns false' => [
            HttpMethod::Delete->value,
            [
                Resolver::class => 'resolver_class',
                Transformer::class => 'transformer_class',
            ],
            false,
        ];

        // Edge cases
        yield 'Empty attributes returns false' => [
            HttpMethod::Get->value,
            [],
            false,
        ];
    }
}
