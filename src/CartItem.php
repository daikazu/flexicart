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
     *
     * @var Fluent<string, mixed>
     */
    public Fluent $attributes;

    /**
     * Item-specific conditions (discounts, add-ons)
     *
     * @var Collection<int, ConditionInterface>
     */
    public Collection $conditions;

    /**
     * Create a new cart item instance.
     *
     * @param  array<string, mixed>  $item
     *
     * @throws PriceException
     */
    public function __construct(array $item)
    {
        $idValue = $item['id'] ?? '';
        $this->id = is_int($idValue) || is_string($idValue) ? $idValue : '';
        $this->name = is_string($item['name'] ?? null) ? $item['name'] : '';

        // Convert price to Price object if it's not already
        $priceValue = $item['price'] ?? 0;
        if ($priceValue instanceof Price) {
            $this->price = $priceValue;
        } elseif (is_int($priceValue) || is_float($priceValue) || is_string($priceValue)) {
            $this->price = new Price($priceValue);
        } else {
            $this->price = new Price(0);
        }

        $quantityValue = $item['quantity'] ?? 1;
        $this->quantity = max(1, is_int($quantityValue) ? $quantityValue : (is_numeric($quantityValue) ? (int) $quantityValue : 1));

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

        /** @var Collection<int, ConditionInterface> $emptyConditions */
        $emptyConditions = collect();
        $this->conditions = $emptyConditions;

        // Set conditions if provided
        $conditionsValue = $item['conditions'] ?? [];
        if (is_array($conditionsValue) || $conditionsValue instanceof Collection) {
            foreach ($conditionsValue as $condition) {
                if ($condition instanceof ConditionInterface) {
                    $this->conditions->push($condition);
                }
            }
        }

        $taxableValue = $item['taxable'] ?? true;
        $this->taxable = is_bool($taxableValue) ? $taxableValue : true;
    }

    /**
     * @param  array<string, mixed>  $item
     *
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
     *
     * @param  array<string, mixed>|ConditionInterface  $condition
     */
    public function addCondition(array | ConditionInterface $condition): self
    {

        if (is_array($condition)) {
            $condition = Condition::make($condition);
        }

        // Check if a condition with the same name already exists
        $existingIndex = $this->conditions->search(fn (ConditionInterface $item): bool => $item->name === $condition->name);

        if ($existingIndex !== false) {
            $this->conditions->put($existingIndex, $condition);
        } else {
            $this->conditions->push($condition);
        }

        return $this;
    }

    /**
     * Add multiple conditions to the instance.
     *
     * @param  array<int, array<string, mixed>|ConditionInterface>  $conditions
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

        $this->conditions = $this->conditions->reject(fn (ConditionInterface $condition): bool => $condition->name === $conditionName)->values();

        return $this;
    }

    /**
     * Clear all conditions from the item.
     */
    public function clearConditions(): self
    {
        /** @var Collection<int, ConditionInterface> $emptyConditions */
        $emptyConditions = collect();
        $this->conditions = $emptyConditions;

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

        $this->conditions = $this->conditions->sortBy(function (ConditionInterface $condition): array {
            // Define target priority (lower number = higher priority)
            /** @var array<string, int> */
            $targetPriorities = [
                ConditionTarget::ITEM->value     => 1,
                ConditionTarget::SUBTOTAL->value => 2,
            ];

            $targetValue = $condition->target->value;
            $priority = is_string($targetValue) && isset($targetPriorities[$targetValue]) ? $targetPriorities[$targetValue] : 999;
            $conditionValue = is_int($condition->value) || is_float($condition->value) ? $condition->value : 0;

            return [
                $priority, // Target priority first
                $condition->order, // Custom order second (ascending)
                -$conditionValue, // Value as tiebreaker (descending)
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
            'conditions' => $this->conditions->map(fn (ConditionInterface $condition): array => $condition->toArray())->toArray(),

        ];

    }
}
