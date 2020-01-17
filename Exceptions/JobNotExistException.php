<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Exceptions;

use Matchbox\LisstedFrontend\Service\Redis\Jobs\JobType;
use Throwable;

class JobNotExistException extends \RuntimeException
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $type;

    public function __construct(string $key, string $type = JobType::NOT_SPECIFIED, $code = 0, Throwable $previous = null)
    {
        parent::__construct("Job with key: {$key} and type: {$type} does not exist!", $code, $previous);
        $this->key = $key;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getJobType(): string
    {
        return $this->type;
    }
}