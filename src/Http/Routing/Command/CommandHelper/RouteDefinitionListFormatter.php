<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Routing\Command\CommandHelper;

use PhoneBurner\Pinch\Component\Http\Routing\Definition\DefinitionList;
use PhoneBurner\Pinch\Component\Http\Routing\Definition\RouteDefinition;
use PhoneBurner\Pinch\Component\Http\Routing\Route;
use Symfony\Component\Console\Output\OutputInterface;

use function PhoneBurner\Pinch\Array\array_wrap;
use function PhoneBurner\Pinch\String\class_shortname;

abstract class RouteDefinitionListFormatter
{
    protected const string ALL_METHODS = 'ALL';

    protected const array HEADER_FIELDS = [
        'method' => 'METHOD',
        'path' => 'PATH',
        'attributes' => 'ATTRIBUTES',
    ];

    final public function __construct()
    {
    }

    abstract public function render(
        OutputInterface $output,
        DefinitionList $definitions,
        bool $show_attributes = true,
        bool $show_namespaces = true,
    ): int;

    protected static function formatAttributes(
        RouteDefinition $definition,
        bool $show_attributes = true,
        bool $show_namespaces = true,
    ): array {
        if ($show_attributes === false) {
            return [$definition->getAttributes()[Route::class] ?? ''];
        }

        $attributes = [];
        foreach (self::unwrap($definition->getAttributes()) as $name => $attribute) {
            $attributes[] = \sprintf(
                "%s => %s",
                $show_namespaces ? $name : class_shortname($name),
                $show_namespaces ? $attribute : class_shortname($attribute),
            );
        }

        return $attributes;
    }

    /**
     * @param iterable<string,mixed> $attributes
     * @return \Generator<string, string>
     */
    protected static function unwrap(iterable $attributes): \Generator
    {
        foreach ($attributes as $name => $value) {
            foreach (array_wrap($value) as $attribute) {
                yield $name => (string)$attribute;
            }
        }
    }
}
