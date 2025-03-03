<?php

namespace HiEvents\Values;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;

class MoneyValue
{
    private Money $money;

    public function __construct(Money $money)
    {
        $this->money = $money;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function toFloat(): float
    {
        return $this->money->getAmount()->toFloat();
    }

    /**
     * @throws MathException
     */
    public function toMinorUnit(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    /**
     * @throws MathException
     * @throws MoneyMismatchException
     */
    public function add(MoneyValue $other): MoneyValue
    {
        return new self($this->money->plus($other->getMoney()));
    }

    public static function zero(string $currency): MoneyValue
    {
        return new self(Money::zero($currency));
    }

    /**
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     */
    public static function fromFloat(float $amount, string $currency): MoneyValue
    {
        return new self(Money::of($amount, $currency, null, RoundingMode::HALF_CEILING));
    }

    /**
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     */
    public static function fromMinorUnit(int $amount, string $currency): MoneyValue
    {
        return new self(Money::ofMinor($amount, $currency, null, RoundingMode::HALF_UP));
    }

    public function __toString(): string
    {
        return $this->money;
    }
}
