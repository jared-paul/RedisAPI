<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Jobs;


use DateTime;
use Symfony\Component\HttpFoundation\ParameterBag;

class SoapJob extends Job
{
    private $request;
    private $result;

    public function __construct(String $key, String $type, String $status, DateTime $createdAt, ParameterBag $request)
    {
        parent::__construct($key, $type, $status, $createdAt);
        $this->request = $request;
    }

    /**
     * The search request for the dispatcher to make the soap call
     * @return ParameterBag
     */
    public function getRequest(): ParameterBag
    {
        return $this->request;
    }

    /**
     * The result of the request once completed
     * @return string
     */
    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * Sets the result of the request to be used on completion
     * @param string $result , result of the web request
     */
    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    public function serialize(): string
    {
        return serialize([
            (string)$this->key,
            (string)$this->type,
            (string)$this->status,
            $this->createdAt,
            $this->request,
            $this->result
        ]);
    }

    public function unserialize($serialized)
    {
        $array = unserialize($serialized);

        $this->key = $array[0];
        $this->type = $array[1];
        $this->status = $array[2];
        $this->createdAt = $array[3];
        $this->request = $array[4];
        $this->result = $array[5];
    }
}