<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Stuble;
use Emsifa\Stuble\Factory;

abstract class StubleCommand extends Command
{

    protected $factory;

    public function __construct()
    {
        parent::__construct($this->name);
        $this->factory = new Factory;
    }


    public function getWorkingPath()
    {
        return realpath('.');
    }

}