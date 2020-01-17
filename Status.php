<?php


namespace Matchbox\LisstedFrontend\Service\Redis;


class Status
{
    const WAITING = "waiting";
    const IN_PROGRESS = "in_progress";
    const DONE = "done";
    const FAILED = "failed";
}