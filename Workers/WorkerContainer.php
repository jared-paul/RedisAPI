<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Workers;


use Psr\Log\LoggerInterface;

class WorkerContainer
{
    private $workers;

    private $logger;

    public function __construct(?array $workers, LoggerInterface $logger)
    {
        $this->logger = $logger;

        if ($workers != null)
        {
            $this->workers = $workers;
        }
        else
        {
            $this->workers = [];
        }
    }

    /**
     * Returns all of the workers' names instantiated through this container
     * @return array, and array of worker names
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    /**
     * Creates and starts a worker with specified name (defaults to a UUID if no name is given) and job type
     * Adds to worker array (container) to be organized later
     * @param string $name , name of the worker
     * @param string $jobType , type of job of the worker
     */
    public function createWorker(string $jobType, string $name = "")
    {
        if ($name === "")
        {
            $name = uniqid();
        }

        exec("/var/www/bin/console redis:consume " . $jobType . " " . $name . " > /dev/null &");
    }
}