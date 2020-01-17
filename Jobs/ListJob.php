<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Jobs;


use DateTime;
use Matchbox\LisstedFrontend\BusinessLogic\Input\ListData;

class ListJob extends Job
{
    /**
     * @var ListData
     */
    protected $listData;

    /**
     * @var string
     */
    private $result;

    public function __construct(String $key, String $type, String $status, DateTime $createdAt, ListData $listData)
    {
        parent::__construct($key, $type, $status, $createdAt);
        $this->listData = $listData;
    }

    /**
     * @return ListData
     */
    public function getListData(): ListData
    {
        return $this->listData;
    }

    public function updateListData(ListData $listData)
    {
        $this->listData = $listData;
    }

    /**
     * @param string $result
     */
    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    /**
     * @return string
     */
    public function getResult(): string
    {
        return $this->result;
    }

    public function serialize(): String
    {
        return serialize([
            (string)$this->key,
            (string)$this->type,
            (string)$this->status,
            $this->createdAt,
            $this->listData,
            (string)$this->result
        ]);
    }

    public function unserialize($serialized)
    {
        $array = unserialize($serialized);

        $this->key = $array[0];
        $this->type = $array[1];
        $this->status = $array[2];
        $this->createdAt = $array[3];
        $this->listData = $array[4];
        $this->result = $array[5];
    }

    public function jsonSerialize()
    {
        return [
            "key" => $this->key,
            "type" => $this->type,
            "status" => $this->status,
            "createdAt" => $this->createdAt,
            "listData" => $this->listData,
            "result" => $this->result
        ];
    }
}