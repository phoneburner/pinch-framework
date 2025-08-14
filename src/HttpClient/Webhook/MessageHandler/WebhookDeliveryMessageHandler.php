<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\HttpClient\Webhook\MessageHandler;

use PhoneBurner\Pinch\Component\Http\Domain\ContentType;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\MessageSignature\HttpMessageSignatureFactory;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Component\Http\Request\RequestFactory;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryCompleted;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryFailed;
use PhoneBurner\Pinch\Component\HttpClient\Event\WebhookDeliveryStarted;
use PhoneBurner\Pinch\Component\HttpClient\HttpClientFactory;
use PhoneBurner\Pinch\Component\HttpClient\Webhook\Message\WebhookDeliveryMessage;
use PhoneBurner\Pinch\Component\MessageBus\MessageBus;
use PhoneBurner\Pinch\String\Encoding\Json;
use PhoneBurner\Pinch\Time\Timer\StopWatch;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class WebhookDeliveryMessageHandler
{
    public function __construct(
        private readonly HttpClientFactory $http_client_factory,
        private readonly RequestFactory $request_factory,
        private readonly HttpMessageSignatureFactory $signature_transformer,
        private readonly MessageBus $message_bus,
        private readonly EventDispatcherInterface $event_dispatcher,
    ) {
    }

    public function __invoke(WebhookDeliveryMessage $message): void
    {
        try {
            $this->event_dispatcher->dispatch(new WebhookDeliveryStarted($message));
            $request = $this->createAndSignRequest($message);

            $timer = StopWatch::start();
            $response = $this->http_client_factory->createHttpClient(
                request_timeout_seconds: $message->configuration->request_timeout_seconds,
                connect_timeout_seconds: $message->configuration->connect_timeout_seconds,
            )->sendRequest($request);
            $elapsed = $timer->elapsed();

            if (Psr7::isSuccessful($response)) {
                $this->event_dispatcher->dispatch(new WebhookDeliveryCompleted(
                    $message,
                    $request,
                    $response,
                    $elapsed,
                ));
                return;
            }
        } catch (\Throwable $e) {
            // the exception is reported in the event dispatched below
        }

        $this->event_dispatcher->dispatch(new WebhookDeliveryFailed(
            message: $message,
            request: $request ?? null,
            response: $response ?? null,
            retryable: $message->attempt < $message->configuration->max_retry_attempts,
            exception: $e ?? null,
        ));

        if ($message->attempt < $message->configuration->max_retry_attempts) {
            $this->message_bus->dispatch($message->withNextAttempt());
        }
    }

    public function createAndSignRequest(WebhookDeliveryMessage $message): RequestInterface
    {
        $payload = $message->payload;
        $request = $this->request_factory->createRequest(
            method: HttpMethod::Post,
            uri: $message->configuration->uri,
            headers: [
                HttpHeader::CONTENT_TYPE => ContentType::JSON,
                HttpHeader::IDEMPOTENCY_KEY => $message->webhook_id->toString(),
                ...$message->configuration->extra_headers,
            ],
            body: Psr7::stream(match (true) {
                \is_string($payload), $payload instanceof StreamInterface => $payload,
                \is_array($payload), $payload instanceof \JsonSerializable => Json::encode($payload),
                default => (string)$payload,
            }),
        );

        $signed_request = $this->signature_transformer->sign($request);
        \assert($signed_request instanceof RequestInterface);

        return $signed_request;
    }
}
