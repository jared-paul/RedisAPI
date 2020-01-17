<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Matchbox\LisstedFrontend\Service\Redis\RedisQueue;
use Psr\Log\LoggerInterface;

abstract class AbstractWorker
{
    protected $name;
    protected $jobType;
    protected $redisQueue;
    protected $count = 0;
    protected $stop = false;

    protected $logger;

    public function __construct(?string $name, string $jobType, int $database, LoggerInterface $logger)
    {
        $this->logger = $logger;

        if ($name == null)
        {
            $name = uniqid();
        }

        $this->name = $name;
        $this->jobType = $jobType;
        $this->redisQueue = new RedisQueue(true, $logger, $database);
    }

    /**
     * Returns the name of the worker
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the job type that the worker is related to
     * @return string
     */
    public function getJobType(): string
    {
        return $this->jobType;
    }

    /**
     * Starts and calls repeatedly the workers "onTick" function
     * @param int $quantity , the quantity of jobs to consume (could be none, depends on the type of worker)
     */
    public function consume(int $quantity = -1)
    {
        $this->redisQueue->addWorker($this);

        while ($quantity == -1 || ($this->count < $quantity && $quantity != -1))
        {
            if (!$this->redisQueue->hasWorker($this->jobType, $this->name))
            {
                break;
            }

            $this->onTick();
        }

        $this->redisQueue->removeWorker($this);
        $this->redisQueue->closeConnection();
    }

    /**
     * Arbitrary function that is defined by the custom workers and is called every tick that its alive
     */
    public abstract function onTick();
}