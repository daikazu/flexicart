<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Brick\Math\RoundingMode;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

/**
 * Buy X Get Y Rule - Buy X items, get Y items free or discounted.
 *
 * Example: Buy 2, Get 1 Free (100% off the 3rd item)
 * Example: Buy 3, Get 1 at 50% off
 */
final class BuyXGetYRule extends AbstractRule
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    /**
     * @param  string  $name  Rule name
     * @param  int  $buyQuantity  Number of items to buy
     * @param  int  $getQuantity  Number of items to get at discount
     * @param  float  $getDiscount  Percentage discount on the "get" items (100 = free)
     * @param  string|array<string>  $itemIds  Item IDs this rule applies to (supports wildcards)
     * @param  array<string, mixed>|Fluent<string, mixed>  $attributes
     */
    public function __construct(
        string $name,
        public readonly int $buyQuantity,
        public readonly int $getQuantity,
        public readonly float $getDiscount = 100.0,
        public readonly string|array $itemIds = '*',
        array|Fluent $attributes = [],
        int $order = 0,
        bool $taxable = false
    ) {
        parent::__construct($name, $getDiscount, $attributes, $order, $taxable);
    }

    public function applies(): bool
    {
        $matchingQuantity = $this->getMatchingItemsQuantity();
        $requiredQuantity = $this->buyQuantity + $this->getQuantity;

        return $matchingQuantity >= $requiredQuantity;
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

        // Sort items by price (lowest first) - discount applies to cheapest items
        $sortedItems = $matchingItems->sortBy(fn (CartItem $item): float => $item->unitPrice()->toFloat());

        $totalQuantity = $this->getMatchingItemsQuantity();
        $bundleSize = $this->buyQuantity + $this->getQuantity;

        // Calculate how many complete bundles we can apply
        $bundles = intdiv($totalQuantity, $bundleSize);

        if ($bundles === 0) {
            return Price::zero();
        }

        // Calculate discount: for each bundle, discount applies to getQuantity cheapest items
        $discountableItems = $bundles * $this->getQuantity;
        $discountAmount = 0.0;
        $discounted = 0;

        foreach ($sortedItems as $item) {
            if ($discounted >= $discountableItems) {
                break;
            }

            $itemsToDiscount = min($item->quantity, $discountableItems - $discounted);

            // Calculate the discount for this item
            $itemPrice = $item->unitPrice();
            $discountPerItem = $itemPrice->toFloat() * ($this->getDiscount / 100);
            $itemDiscountTotal = $discountPerItem * $itemsToDiscount;

            $discountAmount += $itemDiscountTotal;
            $discounted += $itemsToDiscount;
        }

        // Return as negative price (discount)
        return new Price(-$discountAmount);
    }

    private function getMatchingItemsQuantity(): int
    {
        return $this->getItemQuantity($this->itemIds);
    }
}
