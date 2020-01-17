<?php


namespace Matchbox\LisstedFrontend\Service\Redis;

use Matchbox\LisstedFrontend\Service\Redis\Exceptions\JobNotExistException;
use Matchbox\LisstedFrontend\Service\Redis\Jobs\Job;
use Matchbox\LisstedFrontend\Service\Redis\Workers\AbstractWorker;
use Psr\Log\LoggerInterface;

class RedisQueue
{
    const DIVIDER = "-";

    const JOB_QUEUE = "job_queue";
    const JOB_PREFIX = "job";

    const WORKER_PREFIX = "worker";

    private $redisInstance;
    private $persistent;
    private $database;

    private $logger;

    /**
     * RedisQueue constructor.
     * @param bool $persistent , whether the connection to redis should be persistent
     * @param int $databaseIndex , the database to use for the current connection
     * @param LoggerInterface $logger
     */
    public function __construct(bool $persistent, LoggerInterface $logger, int $databaseIndex = RedisDatabase::TEST)
    {
        $this->logger = $logger;

        $this->redisInstance = new \Redis();

        $this->persistent = $persistent;
        $this->database = $databaseIndex;
        $this->connect($persistent, $databaseIndex);
    }

    /**
     * @return \Redis
     */
    public function getRedisInstance(): \Redis
    {
        return $this->redisInstance;
    }

    /**
     * Establishes a connection with redis
     * @param bool $persistent , determines whether the connection should be persistent
     * @param int $databaseIndex , the database to use for the current connection
     */
    public function connect(bool $persistent, int $databaseIndex)
    {
        $host = $_ENV["REDIS_HOST"];;
        $port = $_ENV["REDIS_PORT"];

        if ($persistent)
        {
            $this->redisInstance->pconnect($host, $port);
        }
        else
        {
            $this->redisInstance->connect($host, $port);
        }


        $this->redisInstance->select($databaseIndex);
    }

    /**
     * Increments the "connection" key to test if we are still connected to redis
     * @throws \Exception
     */
    public function testConnection()
    {
        try
        {
            $this->redisInstance->incr("connection");
        }
        catch (\Exception $exception)
        {
            throw $exception;
        }
    }

    public function closeConnection()
    {
        $this->redisInstance->close();
    }

    /**
     * Adds a worker to redis, does not instantiate a new worker process
     * @param AbstractWorker $worker , the worker to add
     */
    public function addWorker(AbstractWorker $worker)
    {
        $this->redisInstance->set(self::WORKER_PREFIX . self::DIVIDER . $worker->getJobType() . self::DIVIDER .
                                  $worker->getName(), true);
    }

    /**
     * Removes a worker from redis, does not remove that instantiated process
     * the worker process itself queries redis and kills itself if its key no longer exists
     * @param AbstractWorker $worker , the worker to remove
     */
    public function removeWorker(AbstractWorker $worker)
    {
        $this->redisInstance->del(self::WORKER_PREFIX . self::DIVIDER . $worker->getJobType() . self::DIVIDER .
                                  $worker->getName());
    }

    /**
     * Removes a random worker with a specified job type once it's finished its task
     * @param string $jobType , the job type of the random worker to remove
     */
    public function removeRandomWorker(string $jobType)
    {
        $workers = $this->getAllWorkersFor($jobType);

        $randomWorker = $workers[count($workers) - 1];
        $this->removeKey($randomWorker);
    }

    /**
     * Checks if redis has a worker/key with the specified job type and name
     * @param string $jobType , the job type check for
     * @param string $name , the name to check for
     * @return bool
     */
    public function hasWorker(string $jobType, string $name): bool
    {
        return $this->redisInstance->exists(self::WORKER_PREFIX . self::DIVIDER . $jobType . self::DIVIDER . $name);
    }

    /**
     * Returns an array of redis keys of all current workers for a specified job type
     * @param string $jobType , job type to check for
     * @return array, returns an array of keys of type string
     */
    public function getAllWorkersFor(string $jobType): array
    {
        return $this->redisInstance->keys(self::WORKER_PREFIX . self::DIVIDER . $jobType . self::DIVIDER . "*");
    }

    /**
     * Returns all redis keys with prefix "worker-"
     * @return array, returns an array of keys of type string
     */
    public function getAllWorkers(): array
    {
        return $this->redisInstance->keys(self::WORKER_PREFIX . self::DIVIDER . "*");
    }

    /**
     * Updates redis with the updated instance of the abstract job
     * The job object has stuff that the job in redis does not have since we update the job object in realtime
     * Used for batch updates
     * @param Job $job , the job to be updated
     */
    public function updateJob(Job $job)
    {
        $this->redisInstance->set(self::JOB_PREFIX . self::DIVIDER . $job->getType() . self::DIVIDER .
                                  $job->getKey(), serialize($job));
    }

    /**
     * Adds the job(s) key to redis
     * First: stores and instance of the job to its key
     * Second: adds the job to the waiting queue for workers to take at their leisure
     * @param Job ...$jobs , the jobs to add
     */
    public function addJob(Job... $jobs)
    {
        foreach ($jobs as $job)
        {
            $this->redisInstance->set(self::JOB_PREFIX . self::DIVIDER . $job->getType() . self::DIVIDER .
                                      $job->getKey(), serialize($job));
            $this->pushToWaitingQueue($job);
        }
    }

    public function pushToWaitingQueue(Job $job)
    {
        $job->updateStatus(Status::WAITING);
        $this->redisInstance->lPush($this->toRedisList($job->getType(), $job->getStatus()), self::JOB_PREFIX .
                                                                                            self::DIVIDER .
                                                                                            $job->getType() .
                                                                                            self::DIVIDER .
                                                                                            $job->getKey());
    }

    /**
     * Removes the job's key from redis
     * Used when the job is finished to clean up the redis storage
     * In the instance of "SoapJob" the job is removed when the results are displayed to the user
     * @param Job $job , the job to remove
     */
    public function removeJob(Job $job)
    {
        $this->removeKey(self::JOB_PREFIX . self::DIVIDER . $job->getType() . self::DIVIDER . $job->getKey());
    }

    /**
     * Generic function to remove a key from redis (removes the value of that key as well)
     * @param string $key , the string key to remove
     */
    public function removeKey(string $key)
    {
        $this->redisInstance->del($key);
    }

    /**
     * Removes a job from the processing queue, used when the job finishes
     * @param Job $job , the job to remove from processing
     */
    public function removeJobFromProcessing(Job $job)
    {
        $this->redisInstance->lRem($this->toRedisList($job->getType(), Status::IN_PROGRESS), self::JOB_PREFIX .
                                                                                             self::DIVIDER .
                                                                                             $job->getType() .
                                                                                             self::DIVIDER .
                                                                                             $job->getKey(), 1);
    }

    public function requeuePastJobs()
    {
        $jobs = $this->getAllJobs();
        //TODO: implement
    }

    /**
     * Returns and removes a job with the specified type from the waiting queue and pushes it to the processing one
     * Updates the status of the job to in progress then updates it in redis
     * @param $jobType , the wanted jobs job type to fetch
     * @return Job|null, the job taken from the waiting queue, can return null in case of exceptions thrown
     * @throws \Exception, in case something goes terribly wrong and there is an unforeseen exception!
     */
    public function fetchJob($jobType): ?Job
    {
        try
        {
            $value = $this->redisInstance->brpoplpush(
                $this->toRedisList($jobType, Status::WAITING),
                $this->toRedisList($jobType, Status::IN_PROGRESS),
                0);

            $job = $this->findJobByKey($value);
            $job->updateStatus(Status::IN_PROGRESS);
            $this->updateJob($job);

            return $job;
        }
        catch (\Exception $exception)
        {
            if (strpos(strtolower($exception->getMessage()), "read error on connection") !== false)
            {
                $this->connect($this->persistent, $this->database);
                return null;
            }
            else
            {
                throw $exception;
            }
        }
    }

    /**
     * Finds a job with the specified key and type in redis
     * @param string $type , the type of job to find
     * @param string $key , the job's key to find
     * @return Job, unserializes the job and returns the job's object from its key
     */
    public function findJobByTypeAndKey(string $type, string $key): Job
    {
        //if the key to search by already has the job prefix in it
        $key = str_replace(self::JOB_PREFIX . self::DIVIDER, "", $key);
        $key = str_replace($type . self::DIVIDER, "", $key);

        if (!$serializedJob = $this->redisInstance->get(self::JOB_PREFIX . self::DIVIDER . $type . self::DIVIDER . $key))
        {
            throw new JobNotExistException($key, $type);
        }

        return unserialize($serializedJob);
    }

    /**
     * Finds a job with the specified key in redis
     * @param string $key , the job's key to find
     * @return Job, unserializes the job and returns the job's object from its key
     */
    public function findJobByKey(string $key): Job
    {
        //if the key to search by already has the job prefix in it
        $key = str_replace(self::JOB_PREFIX . self::DIVIDER, "", $key);

        if (!$serializedJob = $this->redisInstance->get(self::JOB_PREFIX . self::DIVIDER . $key))
        {
            throw new JobNotExistException($key);
        }

        return unserialize($serializedJob);
    }

    /**
     * Returns an array of all jobs that exist
     * @return array, of job objects that exist
     */
    public function getAllJobs(): array
    {
        return $this->getAllJobsByPrefix("*");
    }

    public function getAllJobsByPrefix(string $prefix)
    {
        $jobs = [];

        $jobKeys = $this->redisInstance->keys(self::JOB_PREFIX . self::DIVIDER . $prefix);
        foreach ($jobKeys as $jobKey)
        {
            try
            {
                $job = $this->findJobByKey($jobKey);
                $jobs[] = $job;
            }
            catch (JobNotExistException $exception)
            {
                $this->logger->warning($exception->getMessage());
                throw $exception;
            }
        }

        return $jobs;
    }

    public function getAllJobsBySuffix(string $suffix)
    {
        $jobs = [];

        $jobKeys = $this->redisInstance->keys("*" . $suffix);
        foreach ($jobKeys as $jobKey)
        {
            try
            {
                $job = $this->findJobByKey($jobKey);
                $jobs[] = $job;
            }
            catch (JobNotExistException $exception)
            {
                $this->logger->warning($exception->getMessage());
                throw $exception;
            }
        }

        return $jobs;
    }

    /**
     * Util function to convert a "queue/list" to a string to store in redis
     * @param string $jobType , the job type of the queue
     * @param string $status , the type of status of the queue
     * @return string, the converted queue to string form
     */
    public function toRedisList(string $jobType, string $status): string
    {
        return sprintf(self::JOB_QUEUE . '.%s.%s', $jobType, $status);
    }
}