<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\FastRoute;

use FastRoute\Dispatcher;
use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\Http\Domain\HttpMethod;
use PhoneBurner\Pinch\Component\Http\Routing\Result\MethodNotAllowed;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteFound;
use PhoneBurner\Pinch\Component\Http\Routing\Result\RouteNotFound;
use PhoneBurner\Pinch\Component\Http\Routing\RouterResult;
use PhoneBurner\Pinch\Framework\Http\Routing\FastRoute\FastRouteMatch;

#[Internal]
class FastRouteResultFactory
{
    public function make(FastRouteMatch $match): RouterResult
    {
        if ($match->getStatus() === Dispatcher::METHOD_NOT_ALLOWED) {
            return MethodNotAllowed::make(...\array_map(HttpMethod::instance(...), $match->getMethods()));
        }

        if ($match->getStatus() === Dispatcher::FOUND) {
            return RouteFound::make(
                \unserialize($match->getRouteData(), [
                    'allowed_classes' => true,
                ]),
                $match->getPathVars(),
            );
        }

        return RouteNotFound::make();
    }
}
