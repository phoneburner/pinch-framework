<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function PhoneBurner\Pinch\Array\array_map_with_key;

class PsrMessageInterfaceProcessor implements ProcessorInterface
{
    private const int MAX_BODY_LENGTH = 8192;

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = \array_map(static function (mixed $entry): mixed {
            if ($entry instanceof MessageInterface) {
                $mutated = [
                    'headers' => array_map_with_key(static function (mixed $value, int|string $key): mixed {
                        return match (\mb_convert_case((string)$key, \MB_CASE_TITLE)) {
                            HttpHeader::AUTHORIZATION => '[FILTERED]',
                            default => $value,
                        };
                    }, $entry->getHeaders()),
                    'body' => \mb_strimwidth((string)$entry->getBody(), 0, self::MAX_BODY_LENGTH),
                ];

                if ($entry instanceof RequestInterface) {
                    $mutated['method'] = \strtoupper($entry->getMethod());
                    $mutated['uri'] = (string)$entry->getUri();
                }

                if ($entry instanceof ResponseInterface) {
                    $mutated['status'] = $entry->getStatusCode();
                }

                return $mutated;
            }

            return $entry;
        }, $record->context);

        return $context === $record->context ? $record : $record->with(context: $context);
    }
}
