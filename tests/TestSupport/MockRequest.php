<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\TestSupport;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @phpstan-require-extends TestCase
 */
trait MockRequest
{
    protected function buildMockRequest(): MockRequestBuilder
    {
        return new MockRequestBuilder($this->createMock(ServerRequestInterface::class));
    }

    protected function getMockRequest(array $input = [], array $query = [], array $server = []): ServerRequestInterface
    {
        return $this->buildMockRequest()->withInput($input)->withQuery($query)->withServer($server)->make();
    }
}
