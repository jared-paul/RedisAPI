<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\ListJob;
use Matchbox\LisstedFrontend\Service\Redis\Status;
use Psr\Log\LoggerInterface;

class ReSchedulerWorker extends AbstractWorker
{
    public function __construct(?string $name, int $database, LoggerInterface $logger)
    {
        parent::__construct($name, JobType::RESCHEDULER, $database, $logger);
    }

    /**
     * Checks the progress of all jobs and if they are done checks the last time they were updated
     * compares it with the jobs update schedule and requeues it if past the timestamp
     */
    public function onTick()
    {
        $jobs = $this->redisQueue->getAllJobs();
        $timeNow = new \DateTime();

        foreach ($jobs as $job)
        {
            if ($job instanceof ListJob)
            {
                $timeElapsed = $timeNow->getTimestamp() - $job->getListData()->getLastUpdated()->getTimestamp();

                if ($timeElapsed > $job->getListData()->getUpdateSchedule() && $job->getStatus() == Status::DONE)
                {
                    $this->redisQueue->pushToWaitingQueue($job);
                }
            }
        }

        sleep(60);
    }
}