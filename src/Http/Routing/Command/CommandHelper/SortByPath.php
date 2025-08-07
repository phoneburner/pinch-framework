<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper\RouteDefinitionListSorter;

class SortByPath extends RouteDefinitionListSorter
{
    #[\Override]
    public function __invoke(RouteDefinition $a, RouteDefinition $b): int
    {
        return $this->sort_asc * ($a->getRoutePath() <=> $b->getRoutePath());
    }
}
