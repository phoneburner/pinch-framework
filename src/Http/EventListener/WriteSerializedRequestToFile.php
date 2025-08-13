<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\EventListener;

use PhoneBurner\Pinch\Component\Http\Message\RequestSerializer;
use PhoneBurner\Pinch\Component\Http\RequestAware;
use PhoneBurner\Pinch\Component\Logging\LogTrace;
use PhoneBurner\Pinch\Filesystem\FileWriter;
use Psr\Log\LoggerInterface;

use const PhoneBurner\Pinch\Framework\APP_ROOT;

class WriteSerializedRequestToFile
{
    public function __construct(
        private readonly RequestSerializer $serializer,
        private readonly LogTrace $log_trace,
        private readonly LoggerInterface $logger,
        private readonly string $file_path = APP_ROOT . '/storage/logs/',
    ) {
    }

    public function __invoke(RequestAware $event): void
    {
        try {
            FileWriter::string(
                $this->file_path . $this->log_trace . '_request.http',
                $this->serializer->serialize($event->request),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to write serialized request to file', ['exception' => $e]);
        }
    }
}
