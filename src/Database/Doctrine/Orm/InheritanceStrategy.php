<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Orm;

class InheritanceStrategy
{
    final public const string NONE = 'NONE';
    final public const string SINGLE_TABLE = 'SINGLE_TABLE';
    final public const string JOINED = 'JOINED';
    final public const string TABLE_PER_CLASS = 'TABLE_PER_CLASS';
}
