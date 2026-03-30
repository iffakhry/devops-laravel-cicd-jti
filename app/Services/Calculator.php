<?php

namespace App\Services;

class Calculator
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function add(int|float $a, int|float $b): int|float
    {
        return $a + $b;
    }

    public function subtract(int|float $a, int|float $b): int|float
    {
        return $a - $b;
    }

    public function multiply(int|float $a, int|float $b): int|float
    {
        return $a * $b;
    }

    public function divide(int|float $a, int|float $b): int|float
    {
        if ($b === 0 || $b === 0.0) {
            throw new \InvalidArgumentException('Pembagi tidak boleh nol.');
        }

        return $a / $b;
    }
}
