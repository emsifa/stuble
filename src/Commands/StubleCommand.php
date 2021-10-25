<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Stuble;

abstract class StubleCommand extends Command
{
    protected $stuble;

    public function __construct()
    {
        parent::__construct($this->name);
        $this->stuble = new Stuble();
    }

    /**
     * @return false|string
     */
    public function getWorkingPath(): string|false
    {
        return realpath('.');
    }
}
