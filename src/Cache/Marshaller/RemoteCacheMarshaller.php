<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Cache\Marshaller;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Cache\Exception\CacheMarshallingError;
use PhoneBurner\Pinch\Memory\Bytes;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use PhoneBurner\Pinch\String\Serialization\Marshaller;
use PhoneBurner\Pinch\String\Serialization\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

use function PhoneBurner\Pinch\String\str_truncate;

#[Internal]
class RemoteCacheMarshaller implements MarshallerInterface
{
    /**
     * The threshold at which a value is considered large enough to be compressed.
     * This should be smaller than the network MTU, accounting for the overhead
     * added by base64 encoding and the Redis protocol. (Assuming that the MTU is
     * between 1300 and 1500 bytes.)
     */
    public const int COMPRESSION_THRESHOLD_BYTES = 1200;

    public function __construct(
        private readonly Serializer $serializer = Serializer::Igbinary,
        private readonly bool $compress = true,
        private readonly bool $encode = false,
        private readonly bool $throw_on_serialization_failure = false,
        private readonly LoggerInterface|null $logger = null,
    ) {
        \assert($serializer !== Serializer::Igbinary || \extension_loaded('igbinary'));
    }

    /**
     * Serializes a list of values.
     *
     * When serialization fails for a specific value, no exception should be
     * thrown. Instead, its key should be listed in $failed.
     *
     * We have to set a custom error handler here in order to catch the serialization
     * of resources, which would otherwise throw a warning and not return false.
     * Without this, we couldn't report the failure in the $failed array as required
     * by the interface.
     *
     * @phpstan-ignore parameterByRef.unusedType (Must comply with vendor interface)
     */
    #[\Override]
    public function marshall(array $values, array|null &$failed = null): array
    {
        static $error_handler = static function (int $level, string $message, string $file = '', int $line = 0): never {
            throw new \ErrorException($message, 0, $level, $file, $line);
        };

        $serialized = [];
        $failed ??= [];

        $previous_error_handler = \set_error_handler($error_handler);
        $threshold_bytes = new Bytes(self::COMPRESSION_THRESHOLD_BYTES);

        try {
            foreach ($values as $key => $value) {
                try {
                    $serialized[$key] = Marshaller::serialize(
                        $value,
                        $this->encode ? Encoding::Base64 : null,
                        true,
                        $this->compress,
                        $threshold_bytes,
                        $this->serializer,
                    );
                } catch (\Exception $e) {
                    $this->logger?->error($e->getMessage(), [
                        'key' => $key,
                        'type' => \get_debug_type($value),
                        'exception' => $e,
                    ]);
                    $this->throw_on_serialization_failure
                        ? throw new CacheMarshallingError('Failed to serialize value of type ' . \get_debug_type($value), previous: $e)
                        : $failed[] = $key;
                }
            }
        } finally {
            \set_error_handler($previous_error_handler);
        }

        return $serialized;
    }

    /**
     * Unmarshalls values originally serialized with either the igbinary_serialize()
     * or serialize() functions into their original form, handling input that
     * may be base64 encoded and/or compressed with the gzcompress() function.
     *
     * The framework declares a custom unserialize callback in src/bootstrap.php
     * and configures it with the runtime, so that we throw an exception on
     * deserialization of undefined classes, which would otherwise be silently
     * deserialized as __PHP_Incomplete_Class instances.
     */
    #[\Override]
    public function unmarshall(string $value): mixed
    {
        try {
            return Marshaller::deserialize($value);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to deserialize value', [
                'value' => str_truncate($value, 1024),
                'exception' => $e,
            ]);
            throw new CacheMarshallingError('Failed to deserialize value: ' . str_truncate($value), previous: $e);
        }
    }
}
