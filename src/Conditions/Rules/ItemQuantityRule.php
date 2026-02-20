<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Brick\Math\RoundingMode;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

/**
 * Item Quantity Rule - Apply discount when item quantity meets minimum.
 *
 * Example: Buy 5+ widgets, get 10% off all widgets
 * Example: Buy 10+ of any item, get $5 off per item
 */
final class ItemQuantityRule extends AbstractRule
{
    /**
     * @param  string  $name  Rule name
     * @param  int  $minQuantity  Minimum quantity required to trigger rule
     * @param  float  $discount  Discount amount (negative for discount)
     * @param  ConditionType  $discountType  Whether discount is fixed or percentage
     * @param  string|array<string>  $itemIds  Item IDs this rule applies to (supports wildcards)
     * @param  bool  $perItem  If true, fixed discount applies per item; if false, once per cart
     * @param  array<string, mixed>|Fluent<string, mixed>  $attributes
     */
    public function __construct(
        string $name,
        public readonly int $minQuantity,
        float $discount,
        public readonly ConditionType $discountType = ConditionType::PERCENTAGE,
        public readonly string | array $itemIds = '*',
        public readonly bool $perItem = false,
        array | Fluent $attributes = [],
        int $order = 0,
        bool $taxable = false
    ) {
        parent::__construct($name, $discount, $attributes, $order, $taxable);
        $this->type = $discountType;
    }

    public function applies(): bool
    {
        return $this->getMatchingItemsQuantity() >= $this->minQuantity;
    }

    /**
     * @throws PriceException
     */
    public function getDiscount(): Price
    {
        $matchingItems = $this->getMatchingItems($this->itemIds);

        if ($matchingItems->isEmpty()) {
            return Price::zero();
        }

        if ($this->discountType === ConditionType::PERCENTAGE) {
            // Calculate percentage discount on matching items' subtotal
            $matchingSubtotal = Price::zero();

            foreach ($matchingItems as $item) {
                $matchingSubtotal = $matchingSubtotal->plus($item->subtotal());
            }

            $discountAmount = $matchingSubtotal->multiplyBy(abs($this->value) / 100, RoundingMode::HALF_UP);

            return new Price(-$discountAmount->toFloat());
        }

        // Fixed discount
        if ($this->perItem) {
            // Discount per qualifying item
            $quantity = $this->getMatchingItemsQuantity();
            $perItemDiscount = new Price($this->value);
            $totalDiscount = $perItemDiscount->multiplyBy($quantity, RoundingMode::HALF_UP);

            return $totalDiscount;
        }

        // Single fixed discount
        return new Price($this->value);
    }

    public function formattedValue(): string
    {
        if ($this->discountType === ConditionType::PERCENTAGE) {
            return sprintf('%s%% off when buying %d+', abs($this->value), $this->minQuantity);
        }

        $itemText = $this->perItem ? ' per item' : '';

        try {
            $price = new Price(abs($this->value));

            return sprintf('%s off%s when buying %d+', $price->formatted(), $itemText, $this->minQuantity);
        } catch (PriceException) {
            return sprintf('$%.2f off%s when buying %d+', abs($this->value), $itemText, $this->minQuantity);
        }
    }

    private function getMatchingItemsQuantity(): int
    {
        return $this->getItemQuantity($this->itemIds);
    }
}
