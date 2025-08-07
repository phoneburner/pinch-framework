<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\FastRoute;

use FastRoute\Dispatcher;
use PhoneBurner\Pinch\Attribute\Usage\Internal;

#[Internal]
class FastRouteMatch
{
    /** @var array<string> */
    private array $methods = [];
    private array $path_vars = [];
    private string $route_data = '';
    private readonly int $status;

    public static function make(array $match): self
    {
        try {
            return new self(...$match);
        } catch (\TypeError) {
            throw new \UnexpectedValueException('invalid fastroute response, unexpected type');
        }
    }

    public function __construct(int $status, mixed $methods_or_serialized_data = null, array|null $path_vars = null)
    {
        if ($status === Dispatcher::METHOD_NOT_ALLOWED && ! \is_array($methods_or_serialized_data)) {
            throw new \UnexpectedValueException('invalid fastroute response, expected allowed methods');
        }

        if ($status === Dispatcher::FOUND && ! \is_string($methods_or_serialized_data)) {
            throw new \UnexpectedValueException('invalid fastroute response, expected string of route data');
        }

        if ($status === Dispatcher::FOUND && ! \is_array($path_vars)) {
            throw new \UnexpectedValueException('invalid fastroute response, expected array of path variables');
        }

        $this->status = $status;

        if ($status === Dispatcher::METHOD_NOT_ALLOWED) {
            $this->methods = $methods_or_serialized_data;
        } elseif ($status === Dispatcher::FOUND) {
            $this->route_data = $methods_or_serialized_data;
            $this->path_vars = $path_vars ?? [];
        }
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPathVars(): array
    {
        return $this->path_vars;
    }

    public function getRouteData(): string
    {
        return $this->route_data;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
