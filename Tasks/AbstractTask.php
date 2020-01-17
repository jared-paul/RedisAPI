<?php


namespace Matchbox\LisstedFrontend\Service\Redis\Tasks;


abstract class AbstractTask
{
    abstract function setUp();

    abstract function run();

    abstract function tearDown();
}