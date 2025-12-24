<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Brick\Math\Exception\MathException;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Contracts\CartItemInterface;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

final class CartItem implements CartItemInterface
{
    /**
     * Item Is Taxable
     */
    public bool $taxable = true;

    /**
     * Item identifier
     */
    public readonly int | string $id;

    /**
     * Item name
     */
    public readonly string $name;

    /**
     * Item base price
     */
    public readonly Price $price;

    /**
     * Item quantity
     */
    public int $quantity;

    /**
     * Item-specific attributes (color, size, etc.)
     */
    public Fluent $attributes;

    /**
     * Item-specific conditions (discounts, add-ons)
     */
    public Collection $conditions;

    /**
     * Create a new cart item instance.
     *
     * @throws PriceException
     */
    public function __construct(array $item)
    {
        $this->id = $item['id'];
        $this->name = $item['name'];

        // Convert price to Price object if it's not already
        $this->price = $item['price'] instanceof Price ? $item['price'] : new Price($item['price']);

        $this->quantity = max(1, (int) ($item['quantity'] ?? 1)); // Ensure quantity is at least 1

        if (isset($item['attributes'])) {
            if (is_array($item['attributes'])) {
                $this->attributes = fluent($item['attributes']);
            } elseif ($item['attributes'] instanceof Fluent) {
                $this->attributes = $item['attributes'];
            } else {
                $this->attributes = fluent([]);
            }
        } else {
            $this->attributes = fluent([]);
        }

        $this->conditions = collect();

        // Set conditions if provided
        if (isset($item['conditions'])) {
            foreach ($item['conditions'] as $condition) {
                if ($condition instanceof ConditionInterface) {
                    $this->conditions->push($condition);
                }
            }
        }
        $this->taxable = $item['taxable'] ?? true;
    }

    /**
     * @throws PriceException
     */
    public static function make(array $item): CartItem
    {
        return new self($item);
    }

    /**
     * Set the item quantity.
     *
     * @return $this
     */
    public function setQuantity(int $quantity): self
    {

        $this->quantity = abs($quantity);

        return $this;
    }

    /**
     * Add a condition to the item.
     * If a condition with the same name already exists, it will be overwritten.
     */
    public function addCondition(array | ConditionInterface $condition): self
    {

        if (is_array($condition)) {
            $condition = Condition::make($condition);
        }

        // Check if a condition with the same name already exists
        /** @phpstan-ignore-next-line */
        $existingIndex = $this->conditions->search(fn ($item) => $item->name === $condition->name);

        if ($existingIndex !== false) {
            $this->conditions->put($existingIndex, $condition);
        } else {
            $this->conditions->push($condition);
        }

        return $this;
    }

    /**
     * Add multiple conditions to the instance.
     */
    public function addConditions(array $conditions): self
    {
        foreach ($conditions as $condition) {
            $this->addCondition($condition);
        }

        return $this;
    }

    /**
     * Remove a condition from the item by name.
     */
    public function removeCondition(string $conditionName): self
    {

        $this->conditions = $this->conditions->reject(fn ($condition) => $condition->name === $conditionName)->values();

        return $this;
    }

    /**
     * Clear all conditions from the item.
     */
    public function clearConditions(): self
    {
        $this->conditions = collect();

        return $this;
    }

    /**
     * Get the item unit price.
     */
    public function unitPrice(): Price
    {
        return $this->price;
    }

    /**
     * Calculate the subtotal (price with conditions × quantity).
     *
     * @throws MathException
     * @throws PriceException
     * @throws MoneyMismatchException
     * @throws UnknownCurrencyException
     */
    public function subtotal(): Price
    {

        $compoundDiscounts = config('flexicart.compound_discounts', false);
        $originalUnitPrice = $this->unitPrice();
        $unitPrice = $originalUnitPrice;
        $subtotalAdjustments = new Price(0);
        $fixedSubtotalAdjustments = new Price(0);

        $this->conditions = $this->conditions->sortBy(function ($condition) {
            // Define target priority (lower number = higher priority)
            $targetPriorities = [
                ConditionTarget::ITEM->value     => 1,
                ConditionTarget::SUBTOTAL->value => 2,

            ];

            return [
                $targetPriorities[$condition->target->value] ?? 999, // Target priority first
                $condition->order, // Custom order second (ascending)
                -$condition->value, // Value as tiebreaker (descending)
            ];
        });

        foreach ($this->conditions as $condition) {

            // TARGET IS ITEM
            if ($condition->target === ConditionTarget::ITEM
            ) {

                if ($condition->type === ConditionType::PERCENTAGE) {

                    $basePrice = $compoundDiscounts ? $unitPrice : $originalUnitPrice;
                    $unitPrice = $unitPrice->plus($condition->calculate($basePrice));

                } elseif ($condition->type === ConditionType::FIXED) {
                    $unitPrice = $unitPrice->plus($condition->calculate());
                }

            }

            // TARGET IS SUBTOTAL
            if ($condition->target === ConditionTarget::SUBTOTAL
            ) {
                if ($condition->type === ConditionType::PERCENTAGE
                ) {

                    // For compound discounts, use the running total including previous adjustments
                    if ($compoundDiscounts) {
                        $baseSubtotal = $unitPrice->multiplyBy($this->quantity)
                            ->plus($subtotalAdjustments)
                            ->plus($fixedSubtotalAdjustments);
                    } else {
                        // For non-compound, always use the original base subtotal
                        $baseSubtotal = $unitPrice->multiplyBy($this->quantity);
                    }

                    $subtotalAdjustments = $subtotalAdjustments->plus(
                        $condition->calculate($baseSubtotal)
                    );
                } elseif ($condition->type === ConditionType::FIXED) {
                    $fixedSubtotalAdjustments = $fixedSubtotalAdjustments->plus($condition->calculate());
                }
            }

        }

        $calculatedSubtotal = $unitPrice->multiplyBy($this->quantity)
            ->plus($subtotalAdjustments)
            ->plus($fixedSubtotalAdjustments);

        // Ensure subtotal is never negative
        if ($calculatedSubtotal->toFloat() < 0) {
            return new Price(0);
        }

        return $calculatedSubtotal;
    }

    /**
     * Calculate and retrieve the unadjusted subtotal based on price and quantity.
     *
     * @throws MathException
     * @throws PriceException
     */
    public function unadjustedSubtotal(): Price
    {
        return $this->price
            ->multiplyBy($this->quantity);
    }

    /**
     * Convert the item to an array.
     *
     * @throws MathException
     * @throws PriceException
     * @throws MoneyMismatchException
     * @throws UnknownCurrencyException
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'price'      => $this->price,
            'quantity'   => $this->quantity,
            'unitPrice'  => $this->unitPrice(),
            'subtotal'   => $this->subtotal(),
            'attributes' => $this->attributes->toArray(),
            'conditions' => $this->conditions->map(fn ($condition) => $condition->toArray())->toArray(),

        ];

    }
}
