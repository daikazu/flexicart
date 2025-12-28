<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Brick\Money\Context;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Daikazu\Flexicart\Exceptions\PriceException;
use InvalidArgumentException;
use Stringable;

/**
 * Price Data Transfer Object
 *
 * This class encapsulates price values and provides methods for formatting and calculations.
 * It ensures consistent handling of prices throughout the application.
 */
final class Price implements Stringable
{
    /**
     * The internal price value stored as a float
     */
    private Money $money;

    private readonly string $currency;

    private readonly string $locale;

    /**
     * Create a new Price instance
     *
     * @throws PriceException
     */
    public function __construct(int | float | string | Money $value)
    {
        $currencyConfig = config('flexicart.currency', 'USD');
        $this->currency = is_string($currencyConfig) ? $currencyConfig : 'USD';

        $localeConfig = config('flexicart.locale', 'en_US');
        $this->locale = is_string($localeConfig) ? $localeConfig : 'en_US';

        try {
            $this->money = $value instanceof Money ? $value : Money::of($value, $this->currency);
        } catch (NumberFormatException | RoundingNecessaryException | UnknownCurrencyException $e) {

            throw new PriceException($e->getMessage());
        }

    }

    /**
     * Create a Price instance from a value
     */
    public static function from(int | float | string | Money $value): self
    {
        return new self($value);
    }

    /**
     * Create a Price instance with zero value
     *
     * @throws PriceException
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Get the value in minor (lowest currency) denomination.
     *
     * @throws MathException
     */
    public function getMinorValue(): int
    {
        $minorAmount = $this->money->getMinorAmount();

        return $minorAmount->toInt();
    }

    /**
     * Converts the current money instance to a RationalMoney instance https://github.com/brick/money#advanced-calculations
     *
     * @return RationalMoney The rational representation of the current money instance
     */
    public function toRational(): RationalMoney
    {
        return $this->money->toRational();
    }

    /**
     * Add another price to this one and return a new Price instance
     *
     * @throws MathException|MoneyMismatchException|UnknownCurrencyException|PriceException
     */
    public function plus(self | int | float $price): self
    {
        $addMoney = $price instanceof self
            ? $price->money
            : Money::of($price, $this->currency);

        return new self($this->money->plus($addMoney));

    }

    /**
     * Subtract another price from this one and return a new Price instance
     *
     * @throws MathException|MoneyMismatchException
     * @throws PriceException
     */
    public function subtract(self | int | float $price): self
    {
        try {
            $subMoney = $price instanceof self
                ? $price->money
                : Money::of($price, $this->currency);
        } catch (NumberFormatException | RoundingNecessaryException | UnknownCurrencyException $e) {

            throw new PriceException($e->getMessage());
        }

        // Ensure no negative prices
        $result = $this->money->minus($subMoney);
        if ($result->isNegative()) {
            try {
                $result = Money::of(0, $this->currency);
            } catch (NumberFormatException | RoundingNecessaryException | UnknownCurrencyException $e) {

                throw new PriceException($e->getMessage());
            }
        }

        return new self($result);

    }

    /**
     * Multiply this price by a factor and return a new Price instance
     *
     * @param  int|float  $factor  The factor to multiply by
     * @param  RoundingMode|null  $roundingMode  The rounding mode to use (defaults to HALF_UP if not specified)
     *
     * @throws MathException
     * @throws PriceException
     */
    public function multiplyBy(int | float $factor, ?RoundingMode $roundingMode = null): self
    {
        // Use HALF_UP as the default rounding mode if none is specified
        $mode = $roundingMode ?? RoundingMode::HALF_UP;

        return new self($this->money->multipliedBy($factor, $mode));
    }

    /**
     * Divide this price by a divisor and return a new Price instance
     *
     * @throws MathException
     * @throws PriceException
     * @throws InvalidArgumentException If the divisor is zero
     */
    public function divideBy(int | float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }

        return new self($this->money->dividedBy($divisor));
    }

    /**
     * Apply a percentage to this price and return a new Price instance
     *
     * @throws MathException|MoneyMismatchException
     * @throws PriceException
     */
    public function percentage(float $percentage): self
    {
        $amount = $this->money->toRational()->multipliedBy($percentage / 100);

        return new self($this->money->plus($amount));
    }

    /**
     * Format the price as a string with the specified number of decimal places
     */
    public function formatted(): string
    {
        return $this->money->formatTo($this->locale);
    }

    /**
     * Alias for formatted() method
     */
    public function format(): string
    {
        return $this->formatted();
    }

    public function toFloat(): float
    {
        return $this->money->getAmount()->toFloat();
    }

    /**
     * Retrieve the current Money context
     */
    public function getContext(): Context
    {
        return $this->money->getContext();
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Convert the price to a string
     */
    public function __toString(): string
    {
        return $this->formatted();
    }
}
