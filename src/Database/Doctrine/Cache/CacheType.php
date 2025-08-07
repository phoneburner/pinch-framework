<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Database\Doctrine\Cache;

enum CacheType: string
{
    case Metadata = 'metadata';
    case Query = 'query';
    case Result = 'result';
    case Entity = 'entity';
}
