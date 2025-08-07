<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Cache\Marshaller;

use PhoneBurner\Pinch\Component\Cache\Exception\CacheMarshallingError;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Framework\Cache\Marshaller\RemoteCacheMarshaller;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\LazyObject;
use PhoneBurner\Pinch\Framework\Tests\Fixtures\Mirror;
use PhoneBurner\Pinch\String\Serialization\Marshaller;
use PhoneBurner\Pinch\String\Serialization\Serializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

final class RemoteCacheMarshallerTest extends TestCase
{
    #[DataProvider('providesSimpleTestValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function simpleValuesAreAlwaysMarshalledAsSerializedPhp(
        Serializer $serializer,
        mixed $value,
        string $serialized,
    ): void {
        $sut = new RemoteCacheMarshaller($serializer, true, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertSame($marshalled, $serialized);
        self::assertSame($value, $sut->unmarshall($marshalled));
    }

    public static function providesSimpleTestValuesWithSerializer(): \Generator
    {
        foreach (Serializer::cases() as $serializer) {
            yield $serializer->name . '_null' => [$serializer, null, "N;"];
            yield $serializer->name . '_bool_false' => [$serializer, false, "b:0;"];
            yield $serializer->name . '_bool_true' => [$serializer, true, "b:1;"];
            yield $serializer->name . '_array_empty' => [$serializer, [], 'a:0:{}'];
            yield $serializer->name . '_int_zero' => [$serializer, 0, "i:0;"];
            yield $serializer->name . '_int_one' => [$serializer, 1, "i:1;"];
            yield $serializer->name . '_float_zero' => [$serializer, 0.0, "d:0;"]; // also covers -0.0 case, as -0.0 === 0.0
            yield $serializer->name . '_float_neg_zero' => [$serializer, -0.0, "d:0;"];
            yield $serializer->name . '_string_empty' => [$serializer, "", 's:0:"";'];
            yield $serializer->name . '_string_zero' => [$serializer, "0", 's:1:"0";'];
            yield $serializer->name . '_string_one' => [$serializer, "1", 's:1:"1";'];
        }
    }

    #[DataProvider('providesScalarTestValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function scalarValuesAreSerialized(Serializer $serializer, mixed $value): void
    {
        $sut = new RemoteCacheMarshaller($serializer, true, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesSerializerHeader($serializer, $marshalled);
        self::assertSame($value, $sut->unmarshall($marshalled));
    }

    public static function providesScalarTestValuesWithSerializer(): \Generator
    {
        foreach (Serializer::cases() as $serializer) {
            yield $serializer->name . '_int' => [$serializer, 12345];
            yield $serializer->name . '_float' => [$serializer, 12345.6789];
            yield $serializer->name . '_string' => [$serializer, "hello world"];
            yield $serializer->name . '_indexed_array' => [$serializer, ['foo' => 'bar', 'baz' => 42]];
            yield $serializer->name . '_packed_array' => [$serializer, [1, 2, 3]];
            yield $serializer->name . '_string_array' => [$serializer, ['foo', 'bar', 'baz']];
        }
    }

    #[DataProvider('providesComplexTestValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function complexValuesAreSerialized(Serializer $serializer, mixed $value): void
    {
        $sut = new RemoteCacheMarshaller($serializer, false, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesSerializerHeader($serializer, $marshalled);
        self::assertEquals($value, $sut->unmarshall($marshalled));
    }

    public static function providesComplexTestValuesWithSerializer(): \Generator
    {
        foreach (Serializer::cases() as $serializer) {
            yield [$serializer, new Mirror()];
            yield [$serializer, new \DateTimeImmutable()];
            yield [$serializer, [
                'foo' => new Mirror(),
                'bar' => new \DateTimeImmutable(),
                'baz' => 42,
            ]];
        }
    }

    #[DataProvider('providesSimpleTestValuesWithSerializer')]
    #[DataProvider('providesScalarTestValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function simpleAndScalarValuesCanBeBase64Encoded(
        Serializer $serializer,
        mixed $value,
        mixed $dummy = null,
    ): void {
        $sut = new RemoteCacheMarshaller($serializer, true, true);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesRegularExpression('#^base64:[a-zA-Z0-9/+]*={0,2}$#', $marshalled);
        self::assertSame($value, $sut->unmarshall($marshalled));
    }

    #[DataProvider('providesComplexTestValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function complexValuesCanBeBase64Encoded(Serializer $serializer, mixed $value,): void
    {
        $sut = new RemoteCacheMarshaller($serializer, true, true);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesRegularExpression('#^base64:[a-zA-Z0-9/+]*={0,2}$#', $marshalled);
        self::assertEquals($value, $sut->unmarshall($marshalled));
    }

    #[DataProvider('providesSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function scalarValuesCanBeCompressed(Serializer $serializer): void
    {
        $value = \str_repeat('a', RemoteCacheMarshaller::COMPRESSION_THRESHOLD_BYTES + 1);

        $sut = new RemoteCacheMarshaller($serializer, true, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesRegularExpression('#^\x78\x01|\x78\x5E|\x78\x9C|\x78\xDA#', $marshalled);
        self::assertSame($value, $sut->unmarshall($marshalled));
    }

    #[DataProvider('providesSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function largeComplexValuesCanBeMarshalled(Serializer $serializer): void
    {
        $value = [
            'members' => [
                new Mirror(),
                new Mirror(),
                new Mirror(),
                new Mirror(),
            ],
            'bar' => new \DateTimeImmutable(),
            'baz' => 42,
            'qux' => \str_repeat('a', RemoteCacheMarshaller::COMPRESSION_THRESHOLD_BYTES + 1),
        ];

        $sut = new RemoteCacheMarshaller($serializer, false, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesSerializerHeader($serializer, $marshalled);
        self::assertEquals($value, $sut->unmarshall($marshalled));
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Igbinary)->unmarshall($marshalled));
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Php)->unmarshall($marshalled));

        $sut = new RemoteCacheMarshaller($serializer, true, false);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesRegularExpression('#^\x78\x01|\x78\x5E|\x78\x9C|\x78\xDA#', $marshalled);
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Igbinary)->unmarshall($marshalled));
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Php)->unmarshall($marshalled));

        $sut = new RemoteCacheMarshaller($serializer, true, true);
        ['foo' => $marshalled] = $sut->marshall(['foo' => $value], $failed);

        self::assertEmpty($failed);
        self::assertMatchesRegularExpression('#^base64:[a-zA-Z0-9/+]*={0,2}$#', $marshalled);
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Igbinary)->unmarshall($marshalled));
        self::assertEquals($value, new RemoteCacheMarshaller(Serializer::Php)->unmarshall($marshalled));
    }

    #[DataProvider('providesSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function multipleValuesCanBeMarshalled(Serializer $serializer): void
    {
        $values = [
            'int' => 12345,
            'float' => 12345.6789,
            'string' => ["hello world"],
            'indexed_array' => [['foo' => 'bar', 'baz' => 42]],
            'packed_array' => [[1, 2, 3]],
            'string_array' => [['foo', 'bar', 'baz']],
            'object' => new Mirror(),
            'object_array' => [
                'foo' => new Mirror(),
                'bar' => new \DateTimeImmutable(),
                'baz' => 42,
            ],
        ];

        $sut = new RemoteCacheMarshaller($serializer, true, false);
        $marshalled = $sut->marshall($values, $failed);

        self::assertEmpty($failed);
        foreach ($values as $key => $value) {
            self::assertEquals($value, $sut->unmarshall($marshalled[$key]));
        }

        $sut = new RemoteCacheMarshaller($serializer, true, true);
        $marshalled = $sut->marshall($values, $failed);

        self::assertEmpty($failed);
        foreach ($values as $key => $value) {
            self::assertMatchesRegularExpression('#^base64:[a-zA-Z0-9/+]*={0,2}$#', $marshalled[$key]);
            self::assertEquals($value, $sut->unmarshall($marshalled[$key]));
        }
    }

    #[DataProvider('providesUnserializableValuesWithSerializer')]
    #[Test]
    #[WithoutErrorHandler]
    public function unserializableValuesAreNotMarshalled(Serializer $serializer, mixed $value): void
    {
        $sut = new RemoteCacheMarshaller($serializer, true, false);
        $marshalled = $sut->marshall(['foo' => $value], $failed);

        self::assertSame([], $marshalled);
        self::assertSame(['foo'], $failed);
    }

    public static function providesUnserializableValuesWithSerializer(): \Generator
    {
        foreach (Serializer::cases() as $serializer) {
            yield $serializer->name . '_closure' => [$serializer, fn(): int => 42];
            yield $serializer->name . '_closure_wrapper' => [$serializer, new LazyObject(fn(): object => self::createStub(Mirror::class))];
            yield $serializer->name . '_resource' => [$serializer, \fopen('php://temp', 'rb+')];
        }

        // Only Igbinary can properly fail to serialize resources that are contained in other values
        // if we switch out the error handler for one that can catch the \E_DEPRECATED error, that it
        // triggers, otherwise, it would serialize the resource as `null`
        // PHP's `serialize` will silently ignore them and instead cast it to 0, and serialize that.
        yield Serializer::Igbinary->name . '_resource_wrapper' => [Serializer::Igbinary, Psr7::stream()];
    }

    #[DataProvider('providesValuesWithUndefinedClasses')]
    #[Test]
    public function undefinedClassesAreNotMarshalled(string $value): void
    {
        $sut = new RemoteCacheMarshaller();

        try {
            $sut->unmarshall($value);
            self::fail('Expected exception');
        } catch (\Exception $e) {
            self::assertInstanceOf(CacheMarshallingError::class, $e);
            self::assertInstanceOf(\DomainException::class, $e->getPrevious());
            self::assertSame('Class not found: UndefinedClass', $e->getPrevious()->getMessage());
        }
    }

    public static function providesValuesWithUndefinedClasses(): \Generator
    {
        yield ['a:3:{i:0;O:8:"stdClass":0:{}i:1;O:14:"UndefinedClass":0:{}i:2;O:8:"stdClass":0:{}}'];
        yield ["\x00\x00\x00\x02\x14\x03\x06\x00\x17\x08stdClass\x14\x00\x06\x01\x17\x0EUndefinedClass\x14\x00\x06\x02\x1A\x00\x14\x00"];
    }

    public static function providesSerializer(): \Generator
    {
        foreach (Serializer::cases() as $serializer) {
            yield $serializer->name => [$serializer];
        }
    }

    private static function assertMatchesSerializerHeader(Serializer $serializer, mixed $value): void
    {
        match ($serializer) {
            Serializer::Igbinary => self::assertTrue(\str_starts_with((string)$value, Marshaller::IGBINARY_HEADER)),
            Serializer::Php => self::assertSame(1, \strpos((string)$value, ':')),
        };
    }
}
