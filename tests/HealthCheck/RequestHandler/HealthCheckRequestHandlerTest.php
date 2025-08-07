<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\HealthCheck\RequestHandler;

use Laminas\Diactoros\Uri;
use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpStatus;
use PhoneBurner\Pinch\Component\Http\Response\JsonResponse;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthCheck;
use PhoneBurner\Pinch\Framework\HealthCheck\Domain\HealthStatus;
use PhoneBurner\Pinch\Framework\HealthCheck\HealthCheckBuilder;
use PhoneBurner\Pinch\Framework\HealthCheck\RequestHandler\HealthCheckRequestHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;

final class HealthCheckRequestHandlerTest extends TestCase
{
    #[Test]
    #[TestWith([HealthStatus::Pass, HttpStatus::OK])]
    #[TestWith([HealthStatus::Warn, HttpStatus::SERVICE_UNAVAILABLE])]
    #[TestWith([HealthStatus::Fail, HttpStatus::SERVICE_UNAVAILABLE])]
    public function happyPath(HealthStatus $health_status, int $http_status): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn(new Uri('http://localhost/health'));

        $health_check = new HealthCheck(status: $health_status);
        $factory = $this->createMock(HealthCheckBuilder::class);
        $factory->expects($this->once())
            ->method('withLinks')
            ->with(['self' => '/health'])
            ->willReturnSelf();
        $factory->expects($this->once())
            ->method('make')
            ->willReturn($health_check);

        $logger = new NullLogger();
        $handler = new HealthCheckRequestHandler($factory, $logger);
        $response = $handler->handle($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($http_status, $response->getStatusCode());
        self::assertEquals($health_check, $response->getPayload());
        self::assertSame(ContentType::HEALTH_JSON, $response->getHeaderLine(HttpHeader::CONTENT_TYPE));
        self::assertSame('no-store', $response->getHeaderLine(HttpHeader::CACHE_CONTROL));
    }
}
