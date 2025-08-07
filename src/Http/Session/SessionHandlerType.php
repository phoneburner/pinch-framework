<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Session;

use PhoneBurner\Pinch\Attribute\Usage\Contract;
use PhoneBurner\Pinch\Framework\Http\Session\Attribute\MapsToSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\CookieSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\FileSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\InMemorySessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\NullSessionHandler;
use PhoneBurner\Pinch\Framework\Http\Session\Handler\RedisSessionHandler;
use PhoneBurner\Pinch\String\ClassString\ClassString;

use function PhoneBurner\Pinch\Enum\case_attr_fetch;

#[Contract]
enum SessionHandlerType
{
    #[MapsToSessionHandler(RedisSessionHandler::class)]
    case Redis;

    #[MapsToSessionHandler(FileSessionHandler::class)]
    case File;

    #[MapsToSessionHandler(CookieSessionHandler::class)]
    case Cookie;

    #[MapsToSessionHandler(InMemorySessionHandler::class)]
    case InMemory;

    #[MapsToSessionHandler(NullSessionHandler::class)]
    case Null;

    /**
     * @return ClassString<\SessionHandlerInterface>
     */
    public function getSessionHandlerClass(): ClassString
    {
        return case_attr_fetch($this, MapsToSessionHandler::class)->mapsTo();
    }
}
