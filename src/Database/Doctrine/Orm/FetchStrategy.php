<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Orm;

class FetchStrategy
{
    final public const string LAZY = 'LAZY';
    final public const string EAGER = 'EAGER';
    final public const string EXTRA_LAZY = 'EXTRA_LAZY';
}
