<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Domain;

use Crell\AttributeUtils\ParseProperties;

#[\Attribute(\Attribute::TARGET_CLASS)]
class SelfLinkRoute implements ParseProperties
{
    private array $parameters = [];

    public function __construct(public string $route_name = '', public string $name_property = '')
    {
    }

    public function getPathParameters(): array
    {
        return $this->parameters;
    }

    #[\Override]
    public function setProperties(array $properties): void
    {
        $this->parameters = $properties;
    }

    #[\Override]
    public function includePropertiesByDefault(): bool
    {
        return false;
    }

    #[\Override]
    public function propertyAttribute(): string
    {
        return SelfLinkRouteParameter::class;
    }
}
