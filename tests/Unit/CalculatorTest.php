<?php

namespace Tests\Unit;

use App\Services\Calculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    private Calculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new Calculator();
    }

    /** @test */
    public function it_can_add_two_numbers(): void
    {
        $result = $this->calc->add(10, 5);

        $this->assertEquals(15, $result);
    }

    /** @test */
    public function it_can_subtract_two_numbers(): void
    {
        $result = $this->calc->subtract(10, 3);

        $this->assertEquals(7, $result);
    }

    /** @test */
    public function it_can_multiply_two_numbers(): void
    {
        $result = $this->calc->multiply(4, 3);

        $this->assertEquals(12, $result);
    }

    /** @test */
    public function it_can_divide_two_numbers(): void
    {
        $result = $this->calc->divide(10, 2);

        $this->assertEquals(5, $result);
    }

    /** @test */
    public function it_throws_exception_when_dividing_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pembagi tidak boleh nol.');

        $this->calc->divide(10, 0);
    }
}
