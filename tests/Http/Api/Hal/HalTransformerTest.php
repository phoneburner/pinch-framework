<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Http\Api\Hal;

use PhoneBurner\ApiHandler\Transformer;
use PhoneBurner\LinkTortilla\Link;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\StandardRel;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\Embedder;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\HalResource;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\HalTransformer;
use PhoneBurner\Pinch\Framework\Http\Api\Hal\Linker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class HalTransformerTest extends TestCase
{
    #[Test]
    public function transformWrapsTransformerAndUsesLinkerAndEmbedder(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $object = new \stdClass();

        $transformer = $this->createMock(Transformer::class);
        $transformer->expects($this->once())
            ->method('transform')
            ->with($object, $request)
            ->willReturn(['foo' => 'bar']);

        $linker = $this->createMock(Linker::class);
        $linker->expects($this->once())
            ->method('links')
            ->with($object, $request)
            ->willReturn([
                Link::make(StandardRel::SELF, '/foo/bar'),
                Link::make('other', '/foo/baz'),
                Link::make('related', '/foo/bat'),
            ]);

        $embedder = $this->createMock(Embedder::class);
        $embedder->expects($this->once())
            ->method('embed')
            ->with($object, $request)
            ->willReturn([
                'other' => HalResource::make(
                    ['foo' => 'baz'],
                    [Link::make(StandardRel::SELF, '/foo/baz')],
                ),
                'related' => HalResource::make(
                    ['foo' => 'bat'],
                    [Link::make(StandardRel::SELF, '/foo/bat')],
                ),
            ]);

        $sut = new HalTransformer(
            $transformer,
            $linker,
            $embedder,
        );

        $resource = $sut->transform($object, $request);

        self::assertEqualsCanonicalizing(HalResource::make(
            ['foo' => 'bar'],
            [
                Link::make(StandardRel::SELF, '/foo/bar'),
                Link::make('other', '/foo/baz'),
                Link::make('related', '/foo/bat'),
            ],
            [
                'other' => HalResource::make(
                    ['foo' => 'baz'],
                    [Link::make(StandardRel::SELF, '/foo/baz')],
                ),
                'related' => HalResource::make(
                    ['foo' => 'bat'],
                    [Link::make(StandardRel::SELF, '/foo/bat')],
                ),
            ],
        )->jsonSerialize(), $resource->jsonSerialize());
    }

    #[Test]
    public function transformExpectsArray(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $object = new \stdClass();

        $transformer = $this->createMock(Transformer::class);
        $transformer->expects($this->once())
            ->method('transform')
            ->with($object, $request)
            ->willReturn('{foo: bar}');

        $linker = $this->createMock(Linker::class);
        $embedder = $this->createMock(Embedder::class);

        $sut = new HalTransformer(
            $transformer,
            $linker,
            $embedder,
        );

        $this->expectException(\LogicException::class);
        $sut->transform($object, $request);
    }
}
