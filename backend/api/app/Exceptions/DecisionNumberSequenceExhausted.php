<?php

namespace App\Exceptions;

use RuntimeException;

class DecisionNumberSequenceExhausted extends RuntimeException
{
    public function __construct(public readonly int $year)
    {
        parent::__construct("The formal decision number sequence for {$year} is exhausted.");
    }
}
