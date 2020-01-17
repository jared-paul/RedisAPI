<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Jobs;


use DateTime;

class Job implements \Serializable, \JsonSerializable
{
    protected $key;
    protected $type;
    protected $status;
    protected $createdAt;

    public function __construct(String $key, String $type, String $status, DateTime $createdAt)
    {
        $this->key = $key;
        $this->type = $type;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    /**
     * The key of the job that lives in redis
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Returns the type of job see JobType.php
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the current status of the job see Status.php
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Updates the jobs status (waiting, done, etc) see Status.php
     * @param string $status , the current status of the job
     */
    public function updateStatus(string $status)
    {
        $this->status = $status;
    }

    /**
     * The time at which the job was created, could be useful?
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function serialize(): String
    {
        return serialize([
            (string)$this->key,
            (string)$this->type,
            (string)$this->status,
            $this->createdAt,
        ]);
    }

    public function unserialize($serialized)
    {
        $array = unserialize($serialized);

        $this->key = $array[0];
        $this->type = $array[1];
        $this->status = $array[2];
        $this->createdAt = $array[3];
    }

    public function jsonSerialize()
    {
        return [
            "key" => $this->key,
            "type" => $this->type,
            "status" => $this->status,
            "createdAt" => $this->createdAt
        ];
    }
}
