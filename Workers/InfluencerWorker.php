<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Matchbox\LisstedFrontend\BusinessLogic\Dispatcher;
use Matchbox\LisstedFrontend\Database\Repository\ListRepository;
use Matchbox\LisstedFrontend\Enum\HandlerType;
use Matchbox\LisstedFrontend\Service\Redis\Exceptions\JobNotExistException;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\ListJob;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\SoapJob;
use Matchbox\LisstedFrontend\Service\Redis\Status;
use Psr\Log\LoggerInterface;

class InfluencerWorker extends ListWorker
{
    public function __construct(?string $name, int $database, LoggerInterface $logger, Dispatcher $dispatcher, ListRepository $listRepository)
    {
        parent::__construct($name, JobType::INFLUENCER, HandlerType::COMMUNITY, $database, $logger, $dispatcher, $listRepository);
    }
}