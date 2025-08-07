<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Logging;

use PhoneBurner\Pinch\Component\App\App;
use PhoneBurner\Pinch\Component\App\ServiceFactory;
use Psr\Log\LoggerInterface;

interface LoggerServiceFactory extends ServiceFactory
{
    public function __invoke(App $app, string $id): LoggerInterface;
}
