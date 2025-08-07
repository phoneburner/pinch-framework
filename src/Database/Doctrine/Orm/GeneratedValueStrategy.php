<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Orm;

class GeneratedValueStrategy
{
    final public const string AUTO = 'AUTO';
    final public const string SEQUENCE = 'SEQUENCE';
    final public const string TABLE = 'TABLE';
    final public const string IDENTITY = 'IDENTITY';
    final public const string NONE = 'NONE';
    final public const string UUID = 'UUID';
    final public const string CUSTOM = 'CUSTOM';
}
