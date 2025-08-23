<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use Crell\AttributeUtils\ClassAnalyzer;
use PhoneBurner\LinkTortilla\Link;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\HalLinkAttribute;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRoute;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRouteParameter;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\StandardRel;
use PhoneBurner\Pinch\Time\Standards\Rfc3339;
use Psr\Http\Message\ServerRequestInterface;

class RouteDefinitionSelfLinker implements Linker
{
    public function __construct(
        private readonly DefinitionList $definition_list,
        private readonly ClassAnalyzer $attribute_analyzer,
    ) {
    }

    #[\Override]
    public function links(object $object, ServerRequestInterface $request): array
    {
        return [$this->link($object, request: $request)];
    }

    public function link(
        object $object,
        string $rel = StandardRel::SELF,
        ServerRequestInterface|null $request = null,
    ): Link {
        $self_link = $this->attribute_analyzer->analyze($object, SelfLinkRoute::class);
        if ($self_link->route_name === '') {
            return throw new \RuntimeException('Self link not found');
        }

        $route = $this->definition_list->getNamedRoute($self_link->route_name);
        foreach ($self_link->getPathParameters() as $property => $path_parameter) {
            $route = $route->withPathParameter(
                $path_parameter->name,
                $this->format($object->{$property}, $path_parameter),
            );
        }

        $link = Link::make($rel, (string)$route);
        if ($self_link->name_property !== '') {
            $link = $link->withAttribute(HalLinkAttribute::NAME, (string)$object->{$self_link->name_property});
        }

        if ($request?->getQueryParams()) {
            return $link->withHref($link->getHref() . '?' . \http_build_query($request->getQueryParams()));
        }

        return $link;
    }

    private function format(mixed $value, SelfLinkRouteParameter $parameter): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($parameter->format ?: Rfc3339::DATETIME);
        }

        if ($value instanceof \BackedEnum) {
            return (string)$value->value;
        }

        return (string)$value;
    }
}
