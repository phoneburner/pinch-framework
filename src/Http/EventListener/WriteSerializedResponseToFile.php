<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\EventListener;

use PhoneBurner\Pinch\Component\Http\Message\ResponseSerializer;
use PhoneBurner\Pinch\Component\Http\ResponseAware;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Filesystem\FileWriter;
use Psr\Log\LoggerInterface;

use function PhoneBurner\Pinch\String\str_suffix;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

class WriteSerializedResponseToFile
{
    public function __construct(
        private readonly ResponseSerializer $serializer,
        private readonly LogTrace $log_trace,
        private readonly LoggerInterface $logger,
        private readonly string $file_path = APP_ROOT . '/storage/logs/',
    ) {
    }

    public function __invoke(ResponseAware $event): void
    {
        try {
            FileWriter::string(
                str_suffix($this->file_path, '/') . $this->log_trace . '_response.http',
                $this->serializer->serialize($event->response),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to write serialized response to file', ['exception' => $e]);
        }
    }
}
