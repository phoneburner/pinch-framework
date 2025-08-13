<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use PhoneBurner\Pinch\Component\App\Event\KernelExecutionCompleted;
use PhoneBurner\Pinch\Component\App\Event\KernelExecutionStarted;
use PhoneBurner\Pinch\Component\App\Kernel;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Http\Event\EmittingHttpResponseCompleted;
use PhoneBurner\Pinch\Component\Http\Event\EmittingHttpResponseFailed;
use PhoneBurner\Pinch\Component\Http\Event\EmittingHttpResponseStarted;
use PhoneBurner\Pinch\Component\Http\Event\HandlingHttpRequestCompleted;
use PhoneBurner\Pinch\Component\Http\Event\HandlingHttpRequestFailed;
use PhoneBurner\Pinch\Component\Http\Event\HandlingHttpRequestStarted;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ServerErrorResponse;
use PhoneBurner\Pinch\Framework\Http\Request\RequestFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpKernel implements Kernel
{
    public function __construct(
        private readonly RequestFactory $request_factory,
        private readonly RequestHandlerInterface $request_handler,
        private readonly EmitterInterface $emitter,
        private readonly BuildStage $stage,
        private readonly EventDispatcherInterface $event_dispatcher,
    ) {
    }

    #[\Override]
    public function run(ServerRequestInterface|null $request = null): void
    {
        $this->event_dispatcher->dispatch(new KernelExecutionStarted($this));
        try {
            $request ??= $this->request_factory->fromGlobals();
            $this->event_dispatcher->dispatch(new HandlingHttpRequestStarted($request));
            $response = $this->request_handler->handle($request);
            $this->event_dispatcher->dispatch(new HandlingHttpRequestCompleted($request, $response));
        } catch (\Throwable $e) {
            $this->event_dispatcher->dispatch(new HandlingHttpRequestFailed($request, $e));
            $response = $this->stage !== BuildStage::Development ? new ServerErrorResponse() : throw $e;
        }

        try {
            $this->event_dispatcher->dispatch(new EmittingHttpResponseStarted($response));
            $this->emitter->emit($response);
            $this->event_dispatcher->dispatch(new EmittingHttpResponseCompleted($response));
        } catch (\Throwable $e) {
            $this->event_dispatcher->dispatch(new EmittingHttpResponseFailed($response, $e));
            if ($this->stage === BuildStage::Development) {
                throw $e;
            }
            // This is a very bad place to end up; some kind of failure happened
            // while trying to emit the response. We can't send a response back,
            // or even reliably echo out an error message. If we don't suppress
            // the exception here, it's possible we'll leak sensitive information.
            // Getting the "white screen of death" is the best case scenario here.
        } finally {
            $this->event_dispatcher->dispatch(new KernelExecutionCompleted($this));
        }
    }
}
