<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session\Attribute;

use PhoneBurner\Pinch\String\ClassString\ClassString;
use PhoneBurner\Pinch\String\ClassString\MapsToClassString;

/**
 * @implements MapsToClassString<\SessionHandlerInterface>
 */
#[\Attribute]
readonly final class MapsToSessionHandler implements MapsToClassString
{
    /**
     * @param class-string<\SessionHandlerInterface> $class
     */
    public function __construct(public string $class)
    {
    }

    /**
     * @return ClassString<\SessionHandlerInterface>
     */
    public function mapsTo(): ClassString
    {
        /**
         * @var ClassString<\SessionHandlerInterface> $class_string
         */
        $class_string = new ClassString($this->class);
        return $class_string;
    }
}
