<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Routing;

use ArrayIterator;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Generator;
use IteratorAggregate;
use LogicException;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\InMemoryDefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Component\Http\Routing\Match\RouteMatch;
use PhoneBurner\Pinch\Component\Http\Routing\Result\MethodNotAllowed;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteFound;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteNotFound;
use PhoneBurner\Pinch\Component\Http\Routing\RouterResult;
use PhoneBurner\Pinch\Framework\Http\Config\RoutingConfigStruct;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteDispatcherFactory;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteMatch;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteResultFactory;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRouter as SUT;
use PhoneBurner\Pinch\Framework\Tests\TestSupport\MockRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function PhoneBurner\Pinch\Type\narrow;

final class FastRouterTest extends TestCase
{
    use MockRequest;

    private MockObject&DefinitionList&IteratorAggregate $definition_list;

    private MockObject&FastRouteDispatcherFactory $dispatcher_factory;

    private MockObject&Dispatcher $dispatcher;

    private MockObject&FastRouteResultFactory $result_factory;

    private SUT $sut;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $definition_list = $this->createMockForIntersectionOfInterfaces([DefinitionList::class, IteratorAggregate::class]);
        self::assertInstanceOf(MockObject::class, $definition_list);
        self::assertInstanceOf(IteratorAggregate::class, $definition_list);
        self::assertInstanceOf(DefinitionList::class, $definition_list);

        $this->definition_list = $definition_list;
        $this->dispatcher_factory = $this->createMock(FastRouteDispatcherFactory::class);
        $this->result_factory = $this->createMock(FastRouteResultFactory::class);

        $this->sut = new SUT(
            narrow(DefinitionList::class, $this->definition_list),
            $this->dispatcher_factory,
            $this->result_factory,
        );

        $this->dispatcher = $this->createMock(Dispatcher::class);
    }

    #[Test]
    public function resolveByNameReturnsRouteFound(): void
    {
        $route = RouteDefinition::all('/test');
        $this->definition_list->method('getNamedRoute')
            ->with('test')
            ->willReturn($route);

        $result = $this->sut->resolveByName('test');

        self::assertInstanceOf(RouteFound::class, $result);
        self::assertEquals(
            RouteFound::make($route),
            $result,
        );
    }

    #[Test]
    public function resolveByNameReturnsRouteNotFound(): void
    {
        $this->definition_list->method('getNamedRoute')
            ->with('test')
            ->willThrowException(new LogicException());

        $result = $this->sut->resolveByName('test');

        self::assertInstanceOf(RouteNotFound::class, $result);
    }

    /**
     * This is more of an integration test, but useful in verifying we're using
     * fast route as expected.
     */
    #[Test]
    public function resolveForRequestReturnsExpectedResults(): void
    {
        $all_route = RouteDefinition::all('/all[/{id:\d+}]', ['route' => 'all']);
        $get_route = RouteDefinition::get('/get', ['route' => 'get']);

        $definition_list = InMemoryDefinitionList::make(
            $all_route,
            $get_route,
        );

        $sut = new SUT(
            $definition_list,
            new FastRouteDispatcherFactory(
                $this->createMock(LoggerInterface::class),
                new RoutingConfigStruct(false),
            ),
            new FastRouteResultFactory(),
        );

        $get = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Get)
            ->withUri('https://example.com/get')
            ->make();

        $result = $sut->resolveForRequest($get);
        self::assertInstanceOf(RouteFound::class, $result);
        self::assertEquals(RouteMatch::make($get_route), $result->getRouteMatch());

        $post = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Post)
            ->withUri('https://example.com/all/100')
            ->make();

        $result = $sut->resolveForRequest($post);
        self::assertInstanceOf(RouteFound::class, $result);
        self::assertEquals(RouteMatch::make($all_route, ['id' => '100']), $result->getRouteMatch());

        $bad = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Post)
            ->withUri('https://example.com/bad')
            ->make();

        $result = $sut->resolveForRequest($bad);
        self::assertInstanceOf(RouteNotFound::class, $result);

        $bad = $this->buildMockRequest()
            ->withRequestMethod(HttpMethod::Post)
            ->withUri('https://example.com/get')
            ->make();

        $result = $sut->resolveForRequest($bad);
        self::assertInstanceOf(MethodNotAllowed::class, $result);
        self::assertEquals([HttpMethod::Get], $result->getAllowedMethods());
    }

    #[DataProvider('provideFastRouteMatchData')]
    #[Test]
    public function resolveForRequestProvidesCallbackThatLoadsRoutesAndProvidesToFastRouteCollector(
        array $match,
    ): void {
        $route1 = RouteDefinition::all('/all', ['route' => 'data']);
        $route2 = RouteDefinition::get('/get', ['route' => 'data']);

        $this->definition_list->method('getIterator')->willReturn(new ArrayIterator([
            $route1,
            $route2,
        ]));

        $callable = null;
        $this->dispatcher_factory->method('make')
            ->with(self::callback(static function (callable $arg) use (&$callable): bool {
                $callable = $arg;
                return true;
            }))
            ->willReturn($this->dispatcher);

        $this->dispatcher->method('dispatch')
            ->willReturn($match);

        $result = $this->createMock(RouterResult::class);
        $this->result_factory->method('make')
            ->with(FastRouteMatch::make($match))
            ->willReturn($result);

        $this->sut->resolveForRequest($this->getMockRequest());

        // now check that the route collector is given the right data
        $collector = $this->createMock(RouteCollector::class);
        self::assertIsCallable($callable);

        // Explicitly get methods to ensure correct comparison
        $expected_methods_route1 = $route1->getMethods();
        $expected_args_1 = [$expected_methods_route1, '/all', \serialize($route1)];
        $expected_args_2 = [[HttpMethod::Get->value], '/get', \serialize($route2)];

        $collector->expects($this->exactly(2))
            ->method('addRoute')
            ->willReturnCallback(function (...$args) use ($expected_args_1, $expected_args_2): null {
                static $call_index = 0;
                match ($call_index++) {
                    0 => self::assertEquals($expected_args_1, $args, 'Arguments for first addRoute call mismatch'),
                    1 => self::assertEquals($expected_args_2, $args, 'Arguments for second addRoute call mismatch'),
                    default => self::fail('addRoute called more than twice'),
                };

                // addRoute likely returns void or $this, return null is fine for expectation
                return null;
            });

        $callable($collector);
    }

    #[DataProvider('provideTestMatchData')]
    #[Test]
    public function resolveForRequestReturnsFactoryResponse(
        HttpMethod|string $method,
        string $uri,
        array $match,
    ): void {
        $dispatcher = $this->dispatcher;

        $this->dispatcher_factory->method('make')
            ->willReturn($dispatcher);

        $this->dispatcher->method('dispatch')
            ->with(HttpMethod::instance($method)->value, $uri)
            ->willReturn($match);

        $result = $this->createMock(RouterResult::class);
        $this->result_factory->method('make')
            ->with(FastRouteMatch::make($match))
            ->willReturn($result);

        $request = $this->buildMockRequest()
            ->withRequestMethod($method)
            ->withUri($uri)
            ->make();

        self::assertSame($result, $this->sut->resolveForRequest($request));
    }

    public static function provideFastRouteMatchData(): Generator
    {
        yield 'not found' => [[
            Dispatcher::NOT_FOUND,
        ]];

        yield 'method not allowed' => [[
            Dispatcher::METHOD_NOT_ALLOWED,
            [HttpMethod::Get, HttpMethod::Delete],
        ]];

        yield 'found' => [[
            Dispatcher::FOUND,
            'serialized data',
            ['path' => 'data'],
        ]];
    }

    public static function provideTestMatchData(): Generator
    {
        foreach (self::provideFastRouteMatchData() as $label => [$data]) {
            yield 'get that is ' . $label => [
                HttpMethod::Get,
                'foo/bar',
                $data,
            ];

            yield 'post that is ' . $label => [
                HttpMethod::Post,
                'biz/baz',
                $data,
            ];
        }
    }
}
