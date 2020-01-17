<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;

use Matchbox\LisstedFrontend\BusinessLogic\Dispatcher;
use Matchbox\LisstedFrontend\Service\Redis\Exceptions\JobNotExistException;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\SoapJob;
use Matchbox\LisstedFrontend\Service\Redis\Status;
use Psr\Log\LoggerInterface;

class SoapWorker extends AbstractWorker
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(?string $name, int $database, LoggerInterface $logger, Dispatcher $dispatcher)
    {
        parent::__construct($name, JobType::SOAP, $database, $logger);
        $this->dispatcher = $dispatcher;
    }

    /**
     * Fetches a job off the soap waiting queue, consumes it by processing the soap request, and updates the result in the jobs key in redis
     */
    public function onTick()
    {
        try
        {
            $job = $this->redisQueue->fetchJob($this->jobType);

            if ($job != null && $job instanceof SoapJob)
            {
                try
                {
                    $result = $this->dispatcher->dispatchRequest($job->getRequest());
                    $job->setResult(serialize($result));
                    $job->updateStatus(Status::DONE);
                    $this->redisQueue->updateJob($job);
                }
                catch (\Exception $exception)
                {
                    //In the event of the backend/dispatcher failing update the status to failing
                    $this->logger->warning($exception->getMessage());
                    $job->setResult(serialize($exception->getMessage()));
                    $job->updateStatus(Status::FAILED);
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
}