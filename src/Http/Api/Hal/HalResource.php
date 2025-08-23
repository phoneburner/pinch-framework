<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Hal;

use Psr\Link\EvolvableLinkProviderInterface;
use Psr\Link\LinkInterface;

/**
 * (Mostly) Immutable value object representation of a valid HAL Resource that
 * can be serialized into JSON as part of constructing a response. (Note that
 * while we can guarantee the immutability of the reference to non-scalar things
 * used for resource properties and embedded resources, we cannot guarantee that
 * the thing referred to is itself immutable. This could be fixed by cloning
 * properties -- but that is something for another day.)
 *
 * @link https://tools.ietf.org/html/draft-kelly-json-hal-08
 */
class HalResource implements EvolvableLinkProviderInterface, \JsonSerializable
{
    /**
     * @var array<string,mixed>
     */
    private array $properties = [];

    /**
     * @var array<LinkInterface>
     */
    private array $links = [];

    /**
     * @var array<string, HalResource|array<HalResource>>
     */
    private array $embedded = [];

    protected function __construct(iterable $properties = [], iterable $links = [], iterable $embedded = [])
    {
        foreach ($properties as $name => $property) {
            $this->properties[self::filterName($name)] = self::filterProperty($property);
        }

        foreach ($links as $link) {
            if (! ($link instanceof LinkInterface)) {
                throw new \InvalidArgumentException('resource link must be instance of PSR-13 LinkInterface');
            }
            $this->links[\spl_object_id($link)] = $link;
        }

        foreach ($embedded as $name => $resource) {
            $this->embedded[self::filterName($name)] = self::filterEmbedded($resource);
        }
    }

    /**
     * @param array<string,mixed> $properties
     * @param array<LinkInterface> $links
     * @param iterable<string, HalResource|iterable<HalResource>> $embedded
     */
    public static function make(iterable $properties = [], iterable $links = [], iterable $embedded = []): self
    {
        return new self($properties, $links, $embedded);
    }

    /**
     * Note, we have to check using \array_key_exists() because the property
     * could be set to null.
     */
    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    public function getProperty(string $name): mixed
    {
        return $this->hasProperty($name)
            ? $this->properties[$name]
            : throw new \LogicException('resource property "' . $name . '" not defined');
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function withProperty(string $name, mixed $value): static
    {
        $resource = clone $this;
        $resource->properties[self::filterName($name)] = self::filterProperty($value);

        return $resource;
    }

    public function withoutProperty(string $name): static
    {
        $resource = clone $this;
        unset($resource->properties[$name]);

        return $resource;
    }

    /**
     * @return array<LinkInterface>
     */
    #[\Override]
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @return array<LinkInterface>
     */
    #[\Override]
    public function getLinksByRel(string $rel): array
    {
        return \array_filter($this->links, static fn(LinkInterface $link): bool => \in_array($rel, $link->getRels(), true));
    }

    #[\Override]
    public function withLink(LinkInterface $link): static
    {
        $resource = clone $this;
        $resource->links[\spl_object_id($link)] ??= $link;

        return $resource;
    }

    #[\Override]
    public function withoutLink(LinkInterface $link): static
    {
        $resource = clone $this;
        unset($resource->links[\spl_object_id($link)]);

        return $resource;
    }

    public function hasEmbeddedResource(string $name): bool
    {
        return \array_key_exists($name, $this->embedded);
    }

    /**
     * @return HalResource|HalResource[]
     */
    public function getEmbeddedResource(string $name): self|array
    {
        if (! $this->hasEmbeddedResource($name)) {
            throw new \LogicException('Property Not Defined');
        }

        return $this->embedded[$name];
    }

    /**
     * @return array<string, HalResource|array<HalResource>>
     */
    public function getEmbeddedResources(): array
    {
        return $this->embedded;
    }

    public function withEmbeddedResource(string $name, object $embedded_resource): static
    {
        $resource = clone $this;
        $resource->embedded[self::filterName($name)] = self::filterEmbedded($embedded_resource);

        return $resource;
    }

    public function withoutEmbeddedResource(string $name): static
    {
        $resource = clone $this;
        unset($resource->embedded[$name]);

        return $resource;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        $serialized = [];

        foreach ($this->properties as $key => $value) {
            $serialized[$key] = $value;
        }

        $links_by_rel = [];
        foreach ($this->links as $link) {
            $serialized_link = [];
            $serialized_link['href'] = $link->getHref();

            if ($link->isTemplated()) {
                $serialized_link['templated'] = true;
            }

            foreach ($link->getAttributes() as $key => $attribute) {
                $serialized_link[$key] = $attribute;
            }

            foreach ($link->getRels() as $rel) {
                $links_by_rel[$rel][] = $serialized_link;
                if (\property_exists($link, 'is_array')) {
                    $links_by_rel[$rel]['array'] = $link->is_array;
                }
            }
        }

        foreach ($links_by_rel as $rel => $links) {
            $is_array = $links['array'] ?? false;
            unset($links['array']);

            $serialized['_links'][$rel] = \count($links) === 1 && ! $is_array ? \reset($links) : $links;
        }

        foreach ($this->embedded as $key => $value) {
            $serialized['_embedded'][$key] = $value;
        }

        return $serialized;
    }

    private static function filterName(mixed $name): string
    {
        if (\is_string($name) && $name !== '') {
            return $name;
        }

        throw new \InvalidArgumentException('property name must be non-empty string');
    }

    private static function filterProperty(mixed $value): mixed
    {
        return match (true) {
            \is_resource($value) => throw new \InvalidArgumentException('property value cannot be PHP resource'),
            $value instanceof \Closure => throw new \InvalidArgumentException('property value cannot be PHP closure'),
            default => $value,
        };
    }

    /**
     * Validates if passed embedded resource value is either a Resource Object
     * or an array of Resource Objects, as per the HAL spec.
     *
     * @link https://tools.ietf.org/html/draft-kelly-json-hal-08#section-4.1.2
     * @return HalResource|HalResource[]
     */
    private static function filterEmbedded(object|iterable $embedded_resource): self|array
    {
        if ($embedded_resource instanceof self) {
            return $embedded_resource;
        }

        if (! \is_iterable($embedded_resource)) {
            throw new \InvalidArgumentException('embedded resource must be ResourceProvider or array of ResourceProvider objects');
        }

        $array_of_resources = [];
        foreach ($embedded_resource as $resource) {
            $array_of_resources[] = $resource;
        }

        return $array_of_resources;
    }
}
