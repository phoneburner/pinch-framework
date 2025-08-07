<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Component\Http\Routing\Route;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListSorter;

class SortByName extends RouteDefinitionListSorter
{
    #[\Override]
    public function __invoke(RouteDefinition $a, RouteDefinition $b): int
    {
        return $this->sort_asc * ($a->getAttribute(Route::class) <=> $b->getAttribute(Route::class));
    }
}
