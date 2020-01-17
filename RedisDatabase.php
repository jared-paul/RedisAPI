<?php


namespace Matchbox\LisstedFrontend\Service\Redis;


class RedisDatabase
{
    public const SEARCH = 0;
    public const LIST = 1;
    public const TEST = 15;

    public static function fromString(string $database)
    {
        switch (strtolower($database))
        {
            case "search":
                return self::SEARCH;
            case "list":
                return self::LIST;
            case "test":
                return self::TEST;
            default:
                return -1;
        }
    }
}