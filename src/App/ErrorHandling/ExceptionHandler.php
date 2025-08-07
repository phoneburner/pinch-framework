<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\App\ErrorHandling;

interface ExceptionHandler
{
    public function __invoke(\Throwable $e): void;
}
