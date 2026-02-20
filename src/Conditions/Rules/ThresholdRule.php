<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Brick\Math\RoundingMode;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

/**
 * Threshold Rule - Apply discount when subtotal exceeds a minimum amount.
 *
 * Example: Spend $100, get 10% off
 * Example: Spend $200, get $25 off
 */
final class ThresholdRule extends AbstractRule
{
    /**
     * @param  string  $name  Rule name
     * @param  float  $minSubtotal  Minimum subtotal required to trigger rule
     * @param  float  $discount  Discount amount (negative for discount)
     * @param  ConditionType  $discountType  Whether discount is fixed or percentage
     * @param  array<string, mixed>|Fluent<string, mixed>  $attributes
     */
    public function __construct(
        string $name,
        public readonly float $minSubtotal,
        float $discount,
        public readonly ConditionType $discountType = ConditionType::PERCENTAGE,
        array | Fluent $attributes = [],
        int $order = 0,
        bool $taxable = false
    ) {
        parent::__construct($name, $discount, $attributes, $order, $taxable);
        $this->type = $discountType;
    }

    public function applies(): bool
    {
        return $this->subtotal->toFloat() >= $this->minSubtotal;
    }

    /**
     * @throws PriceException
     */
    public function getDiscount(): Price
    {
        if ($this->discountType === ConditionType::PERCENTAGE) {
            // For percentage, calculate based on subtotal
            $discountAmount = $this->subtotal->multiplyBy(abs($this->value) / 100, RoundingMode::HALF_UP);

            // Return negative for discount
            return new Price(-$discountAmount->toFloat());
        }

        // For fixed amount, return the value directly (should be negative for discount)
        return new Price($this->value);
    }

    public function formattedValue(): string
    {
        if ($this->discountType === ConditionType::PERCENTAGE) {
            return sprintf('%s%% off orders over %s', abs($this->value), $this->formatPrice($this->minSubtotal));
        }

        return sprintf('%s off orders over %s', $this->formatPrice(abs($this->value)), $this->formatPrice($this->minSubtotal));
    }

    private function formatPrice(float $amount): string
    {
        try {
            $price = new Price($amount);

            return $price->formatted();
        } catch (PriceException) {
            return sprintf('$%.2f', $amount);
        }
    }
}
