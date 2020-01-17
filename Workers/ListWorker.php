<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use DateInterval;
use DateTime;
use DateTimeZone;
use Matchbox\LisstedFrontend\BusinessLogic\Dispatcher;
use Matchbox\LisstedFrontend\BusinessLogic\Input\KeywordSearchData;
use Matchbox\LisstedFrontend\BusinessLogic\Input\ListData;
use Matchbox\LisstedFrontend\BusinessLogic\Input\ListSearchData;
use Matchbox\LisstedFrontend\BusinessLogic\Input\SearchData;
use Matchbox\LisstedFrontend\Database\Repository\ListRepository;
use Matchbox\LisstedFrontend\Enum\DurationType;
use Matchbox\LisstedFrontend\Enum\HandlerType;
use Matchbox\LisstedFrontend\Service\Redis\Exceptions\JobNotExistException;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\Job;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\ListJob;
use Matchbox\LisstedFrontend\Service\Redis\Status;
use Psr\Log\LoggerInterface;

abstract class ListWorker extends AbstractWorker
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var ListRepository
     */
    protected $listRepository;

    /**
     * @var int
     */
    protected $handlerType;

    public function __construct(?string $name, string $jobType, int $handlerType, int $database, LoggerInterface $logger, Dispatcher $dispatcher, ListRepository $listRepository)
    {
        parent::__construct($name, $jobType, $database, $logger);
        $this->dispatcher = $dispatcher;
        $this->listRepository = $listRepository;
        $this->handlerType = $handlerType;
    }

    /**
     * Fetches a job off the $jobType 's waiting queue, consumes it by processing the soap request, and updates the result in the jobs key in redis
     */
    public function onTick()
    {
        try
        {
            $job = $this->redisQueue->fetchJob($this->jobType);

            if ($job != null && $job instanceof ListJob && $job->getType() == $this->jobType)
            {
                //they could be the same, we just want to make sure. "updateJob" (see below) will update redis with the new listdata for us
                $listData = $this->updateJobListData($job);
                $lastRan = new DateTime();

                try
                {
                    $searchData = $this->convertToSearchData($listData);
                    $result = $this->dispatcher->dispatchSearchData($searchData, $this->handlerType);
                    $job->setResult(serialize($result));
                    $job->updateStatus(Status::DONE);

                    $listData->setLastUpdated($lastRan);
                    $this->listRepository->updateList($listData);
                    $this->listRepository->addDigest($listData->getId(), $job->getType(), serialize($result));

                    $job->updateListData($listData);
                    $this->redisQueue->updateJob($job);
                }
                catch (\Exception $exception)
                {
                    //In the event of the backend/dispatcher failing update the status to failing
                    $this->logger->warning("Redis: " . $exception->getMessage());
                    $job->setResult(serialize($exception->getMessage()));
                    $job->updateStatus(Status::FAILED);
                    $listData->setLastUpdated($lastRan);

                    $job->updateListData($listData);
                    $this->listRepository->updateList($listData);
                    $this->redisQueue->updateJob($job);
                }

                $this->redisQueue->removeJobFromProcessing($job);

                $this->count++;
            }
        }
        catch (JobNotExistException | \Exception $exception)
        {
            $this->logger->warning($exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Updates the redis ListJob with whatever ListData is in the PostGres database
     * list updates can happen 'anywhere' (properties change for example) so we need to call this every job run
     * @param ListJob $listJob , the list job to update
     * @return ListData , the data to update the job with
     */
    private function updateJobListData(ListJob $listJob): ListData
    {
        $oldListData = $listJob->getListData();
        $newListData = $this->listRepository->getSpecificList($oldListData->getId()); //id and projectId are immutable, dont worry

        $listJob->updateListData($newListData);
        return $newListData;
    }

    /**
     * Converts the given list data into search data for the dispatcher to use
     * @param ListData $listData , the list data to convert
     * @return KeywordSearchData|ListSearchData , returns keywordsearchdata for private lists and vice versa
     * @throws \Matchbox\LisstedFrontend\Exception\ApplicationException , can be thrown when converting durationType to string
     * @throws \Exception , can be thrown when creating a new DateTime instance
     */
    private function convertToSearchData(ListData $listData): SearchData
    {
        $to = new DateTime('now', new DateTimeZone('UTC'));
        $from = clone $to;

        $dateSelect = DurationType::convertToString($listData->getDuration());
        $from->sub(new DateInterval("P{$dateSelect}D"));

        if ($listData->isPrivate())
        {
            return new KeywordSearchData($listData->getKeywords(), $from, $to);
        }

        return new ListSearchData($listData->getProfileList()->getId(), $from, $to);
    }
}