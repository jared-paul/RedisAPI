<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Matchbox\LisstedFrontend\BusinessLogic\Dispatcher;
use Matchbox\LisstedFrontend\Database\Repository\ListRepository;
use Matchbox\LisstedFrontend\Enum\HandlerType;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Psr\Log\LoggerInterface;

class ContentWorker extends ListWorker
{
    public function __construct(?string $name, int $database, LoggerInterface $logger, Dispatcher $dispatcher, ListRepository $listRepository)
    {
        parent::__construct($name, JobType::CONTENT, HandlerType::TOP_CONTENT, $database, $logger, $dispatcher, $listRepository);
    }
}