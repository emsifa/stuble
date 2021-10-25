<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Stuble;

abstract class StubleCommand extends Command
{
    /**
     * Stuble instance
     *
     * @var Stuble
     */
    protected Stuble $stuble;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct($this->name);
        $this->stuble = new Stuble();
    }

    /**
     * Get absolute working path
     *
     * @return string
     */
    public function getWorkingPath(): string
    {
        return realpath('.');
    }
}
