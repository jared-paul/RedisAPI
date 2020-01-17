<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Matchbox\LisstedFrontend\Service\Redis\Jobs\Job;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Matchbox\LisstedFrontend\Service\Redis\RedisDatabase;
use Psr\Log\LoggerInterface;

class ReliabilityWorker extends AbstractWorker
{
    private $jobTypeToManage;
    private $amount;

    private $workerContainer;

    public function __construct(?string $name, int $database, string $jobTypeToManage, int $amount, LoggerInterface $logger)
    {
        parent::__construct($name, JobType::RELIABILITY, $database, $logger);
        $this->jobTypeToManage = $jobTypeToManage;
        $this->amount = $amount;
        $this->workerContainer = new WorkerContainer($this->redisQueue->getAllWorkers(), $logger);
    }

    public function onTick()
    {
        $this->cleanJobs(60 * 5, $this->jobTypeToManage); //5 minutes
        $this->confirmWorkers($this->amount, $this->jobTypeToManage);

        sleep(30);
    }

    /**
     * Makes sure that there is always a specified amount of workers for a job type, if not: create them!, if too much: kill them!
     * @param int $amount
     * @param string $jobType
     */
    public function confirmWorkers(int $amount, string $jobType)
    {
        $numberOfWorkers = count($this->redisQueue->getAllWorkersFor($jobType));

        $this->supplyWorkers($numberOfWorkers, $amount, $jobType);
        $this->cleanseWorkers($numberOfWorkers, $amount, $jobType);
    }

    /**
     * Supplies a set amount of workers for a job type if lacking some, does not go over the desired amount
     * @param int $currentAmount , current amount of workers
     * @param int $desiredAmount , desired amount of workers
     * @param string $jobType , job type to supply workers for
     */
    public function supplyWorkers(int $currentAmount, int $desiredAmount, string $jobType)
    {
        $count = $currentAmount;
        while ($count < $desiredAmount)
        {
            $this->workerContainer->createWorker($jobType);
            $count++;
        }
    }

    /**
     * Removes a set amount of workers for a job type if too many are present, does not go under the desired amount
     * @param int $currentAmount , current amount of workers
     * @param int $desiredAmount , desired amount of workers
     * @param string $jobType , job type to remove workers from
     */
    public function cleanseWorkers(int $currentAmount, int $desiredAmount, string $jobType)
    {
        $count = $currentAmount;
        while ($count > $desiredAmount)
        {
            $this->redisQueue->removeRandomWorker($jobType);
            $count--;
        }
    }

    /**
     * Checks to see if any jobs are alive past the designated time, if so: remove them
     * @param int $timeAlive , the desired time elapsed in seconds of a job to see if it needs to be removed
     * @param string $jobType , the job type to clean for
     */
    public function cleanJobs(int $timeAlive, string $jobType)
    {
        try
        {
            $jobs = $this->redisQueue->getAllJobsByPrefix($jobType);
            $timeNow = new \DateTime();

            foreach ($jobs as $job)
            {
                if ($job instanceof Job)
                {
                    $createdAt = $job->getCreatedAt();
                    $timeElapsed = $timeNow->getTimestamp() - $createdAt->getTimestamp();

                    if ($timeElapsed > $timeAlive)
                    {
                        $this->redisQueue->removeJob($job);
                    }
                }
            }
        }
        catch (\Exception $exception)
        {
            $this->logger->warning("Could not clean up jobs, error with retrieving DateTime!");
        }
    }
}