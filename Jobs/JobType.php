<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Jobs;


class JobType
{
    public const NOT_SPECIFIED = "not_specified";
    public const RELIABILITY = "reliability";
    public const RESCHEDULER = "rescheduler";
    public const SOAP = "soap";
    public const INFLUENCER = "influencer";
    public const TWEET = "tweet";
    public const CONTENT = "content";

    public static function listValues(): array
    {
        return [
            self::INFLUENCER,
            self::TWEET,
            self::CONTENT
        ];
    }
}