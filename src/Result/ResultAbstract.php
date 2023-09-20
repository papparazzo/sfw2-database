<?php

namespace SFW2\Database\Result;

abstract class ResultAbstract
{
    abstract public function fetch(): array;

    public function close(): void {

    }
}