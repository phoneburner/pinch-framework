<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\RequestHandler;

use Monolog\Formatter\LogglyFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PhoneBurner\Pinch\Component\Configuration\BuildStage;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\NotFoundResponse;
use PhoneBurner\Pinch\Component\Http\Response\Exceptional\ServerErrorResponse;
use PhoneBurner\Pinch\Component\Http\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class PhpInfoRequestHandler implements RequestHandlerInterface
{
    public function __construct(private BuildStage $build_stage)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
//        error_log('Where does this go???');

        $logger = new Logger(
            'figure-it-out',
            [new StreamHandler('php://stderr', Level::Debug)->setFormatter(new LogglyFormatter())],
        );

        $logger->log(Level::Debug, 'Where does this go???');
//        $fh = fopen('php://stdout','w');
//        fwrite($fh, 'Where does this go??? stdout');
//        fclose($fh);

        if ($this->build_stage !== BuildStage::Development) {
            return new NotFoundResponse();
        }

        $buffer = (static function (): string {
            \ob_start();
            /** @phpstan-ignore-next-line */
            \phpinfo();
            return (string)\ob_get_clean();
        })();

        return $buffer ? new HtmlResponse($buffer) : new ServerErrorResponse();
    }
}
