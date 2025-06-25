<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\CartException;
use Daikazu\Flexicart\Exceptions\PriceException;
use Illuminate\Support\Collection;

final class Cart implements CartInterface
{
    /**
     * The cart items.
     *
     * @var Collection<string, CartItem>
     */
    public Collection $items;

    /**
     * The global cart conditions (applied to subtotal).
     *
     * @var Collection<string, ConditionInterface>
     */
    public Collection $conditions;

    /**
     * Create a new cart instance.
     */
    public function __construct(/**
     * The storage implementation.
     */
        private readonly StorageInterface $storage)
    {
        $data = $this->storage->get();
        $this->items = collect($data['items'] ?? []);
        $this->conditions = collect($data['conditions'] ?? []);
    }

    /**
     * Get a cart by ID.
     *
     * @throws PriceException
     */
    public static function getCartById(string $cartId): ?self
    {
        // Get the storage instance from the application container
        $storage = app(StorageInterface::class);

        $cartData = $storage->getCartById($cartId);

        if ($cartData === null) {
            return null;
        }

        $cart = new self($storage);

        // Convert items to CartItem objects
        $items = collect();
        foreach ($cartData['items'] ?? [] as $itemId => $item) {
            if ($item instanceof CartItem) {
                $items->put($itemId, $item);
            } else {
                $items->put($itemId, new CartItem($item));
            }
        }
        $cart->items = $items;

        $cart->conditions = collect($cartData['conditions'] ?? []);

        return $cart;
    }

    public function cart(): self
    {
        return $this;
    }

    /**
     * Get the cart ID.
     */
    public function id(): string
    {
        return $this->storage->getCartId();
    }

    /**
     * Add an item to the cart.
     *
     * @throws CartException
     * @throws PriceException
     */
    public function addItem(array|CartItem $item): self
    {

        if ($item instanceof CartItem) {
            $this->items->put($item->id, $item);
        } else {

            if (! isset($item['id'])) {
                throw new CartException('Item ID is required');
            }

            if (! isset($item['name'])) {
                throw new CartException('Item name is required');
            }

            if (! isset($item['price'])) {
                throw new CartException('Item price is required');
            }

            $itemId = $item['id'];

            $item = $this->updateExistingItem($item);

            $cartItem = CartItem::make($item);

            $this->items->put($itemId, $cartItem);

        }

        $this->persist();

        return $this;
    }

    /**
     * Update an item in the cart.
     *
     * @throws PriceException
     */
    public function updateItem(string $itemId, array $attributes): self
    {
        if (! $this->items->has($itemId)) {
            return $this; // Return early if item doesn't exist
        }

        $existingItem = $this->items->get($itemId);

        // Create a new item array with the existing item's properties
        $item = [
            'id'         => $existingItem->id,
            'name'       => $existingItem->name,
            'price'      => $existingItem->unitPrice(),
            'quantity'   => $existingItem->quantity,
            'taxable'    => $existingItem->taxable,
            'attributes' => $existingItem->attributes,
        ];

        // Update with new attributes
        if (isset($attributes['name'])) {
            $item['name'] = $attributes['name'];
        }

        if (isset($attributes['price'])) {
            $item['price'] = $attributes['price'];
        }

        if (isset($attributes['quantity'])) {
            $item['quantity'] = $attributes['quantity'];
        }

        if (isset($attributes['taxable'])) {
            $item['taxable'] = $attributes['taxable'];
        }

        if (isset($attributes['attributes'])) {
            $item['attributes'] = fluent($attributes['attributes']);
        }

        // If the existing item has conditions, add them to the new item
        if ($existingItem->conditions->isNotEmpty()) {
            $item['conditions'] = $existingItem->conditions;
        }

        if (isset($attributes['conditions'])) {
            $item['conditions'] = $existingItem->conditions->merge($attributes['conditions']);
        }

        $cartItem = new CartItem($item);
        $this->items->put($itemId, $cartItem);

        $this->persist();

        return $this;
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(string $itemId): self
    {
        $this->items->forget($itemId);
        $this->persist();

        return $this;
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): self
    {
        $this->items = collect();
        $this->persist();

        return $this;
    }

    /**
     * Clear all items from the cart.
     */
    public function reset(): self
    {
        $this->items = collect();
        $this->conditions = collect();
        $this->persist();

        return $this;
    }

    /**
     * Get a specific item from the cart.
     */
    public function item(int|string $itemId): ?CartItem
    {
        return $this->items->get($itemId);
    }

    /**
     * Get all items from the cart.
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Get all conditions from the cart.
     */
    public function conditions(): Collection
    {
        return $this->conditions;
    }

    /**
     * Count the total quantity of all items in the collection.
     *
     * @return int The total quantity of items.
     */
    public function count(): int
    {
        return $this->items->sum(function ($item) {
            return $item->quantity;
        });
    }

    /**
     * Get the count of unique items in the cart.
     *
     * @return int The number of unique items.
     */
    public function uniqueCount(): int
    {
        return $this->items->count();
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Calculate the cart subtotal (sum of all item subtotals).
     *
     * @throws PriceException
     * @throws UnknownCurrencyException
     * @throws MoneyMismatchException
     * @throws MathException
     */
    public function subtotal(): Price
    {
        $subtotalPrice = Price::zero();

        $this->items->each(function ($item) use (&$subtotalPrice): void {
            $itemSubtotal = $item->subtotal();
            $subtotalPrice = $subtotalPrice->plus($itemSubtotal);
        });

        return $subtotalPrice;
    }

    /**
     * Get the subtotal of only taxable cart items
     *
     * @throws PriceException
     * @throws UnknownCurrencyException
     * @throws MoneyMismatchException
     * @throws MathException
     */
    public function getTaxableSubtotal(): Price
    {
        $taxableSubtotal = Price::zero();

        $this->items->each(function ($item) use (&$taxableSubtotal): void {

            // check if the item has the taxable attribute
            if ($item->taxable) {
                $taxableSubtotal = $taxableSubtotal->plus($item->subtotal());
            }
        });

        return $taxableSubtotal;
    }

    /**
     * Add a global condition to the cart.
     * If a condition with the same name already exists, it will be overwritten.
     */
    public function addCondition(array|ConditionInterface $condition): self
    {
        // Check if a condition with the same name already exists
        /** @phpstan-ignore-next-line */
        $existingIndex = $this->conditions->search(fn ($item) => $item->name === $condition->name);

        if ($existingIndex !== false) {
            $this->conditions->put($existingIndex, $condition);
        } else {
            $this->conditions->push($condition);
        }

        $this->persist();

        return $this;
    }

    public function addConditions(array $conditions): self
    {
        foreach ($conditions as $condition) {
            $this->addCondition($condition);
        }

        return $this;
    }

    /**
     * Clear all global conditions.
     */
    public function clearConditions(): self
    {
        $this->conditions = collect();

        $this->persist();

        return $this;
    }

    /**
     * Remove a specific global condition by name.
     */
    public function removeCondition(string $conditionName): self
    {
        /** @phpstan-ignore-next-line */
        $this->conditions = $this->conditions->reject(fn ($condition) => $condition->name === $conditionName)->values();

        $this->persist();

        return $this;
    }

    /**
     * Add a condition to a specific cart item.
     *
     * @param  int|string  $itemId  The ID of the item
     * @param  Condition  $condition  The condition to add
     */
    public function addItemCondition(int|string $itemId, ConditionInterface $condition): self
    {
        if ($this->items->has($itemId)) {
            $item = $this->items->get($itemId);
            $item->addCondition($condition);
            $this->persist();
        }

        return $this;
    }

    /**
     * Remove a specific condition from a cart item.
     *
     * @param  int|string  $itemId  The ID of the item
     * @param  string  $conditionName  The name of the condition to remove
     * @return $this
     */
    public function removeItemCondition(int|string $itemId, string $conditionName): static
    {
        if ($this->items->has($itemId)) {
            $item = $this->items->get($itemId);
            $item->removeCondition($conditionName);
            $this->persist();
        }

        return $this;
    }

    /**
     * Calculate the cart total (subtotal + cart conditions).
     *
     * @throws PriceException
     * @throws UnknownCurrencyException
     * @throws MoneyMismatchException
     * @throws MathException
     */
    public function total(): Price
    {
        $compoundDiscounts = config('flexicart.compound_discounts', false);
        $subtotal = $this->subtotal(); // subtotal of all items
        $taxableSubtotal = $this->getTaxableSubtotal(); // subtotal of items that are taxable to use when calculating conditions that have a target taxable
        $originalSubtotal = $subtotal;
        $originalTaxableSubtotal = $taxableSubtotal;
        $total = $subtotal;
        $currentTaxableSubtotal = $taxableSubtotal; // Track the current taxable subtotal after applying conditions
        $taxableAdjustments = Price::zero(); // Track adjustments from taxable subtotal conditions

        // Sort conditions by target priority and type (taxable conditions last)
        $sortedConditions = $this->conditions->sortBy(function ($condition) {
            // Define target priority (lower number = higher priority)

            $targetPriorities = [
                ConditionTarget::SUBTOTAL->value => 1, // Apply to subtotal first (before taxes)
                ConditionTarget::TAXABLE->value  => 2, // Apply to taxable items
            ];

            return [
                $targetPriorities[$condition->target->value] ?? 999, // Default to end if unknown target
                $condition->taxable ? 2 : 1, // Tax conditions last within each target group
                $condition->order,
                -$condition->value, // Negative for descending order by value
            ];
        });

        $sortedConditions->each(function ($condition) use (&$total, &$currentTaxableSubtotal, &$taxableAdjustments, $originalSubtotal, $originalTaxableSubtotal, $compoundDiscounts): void {

            // Use original values or current values based on compound_discounts setting
            $baseSubtotal = $compoundDiscounts ? $total : $originalSubtotal;
            // For taxable conditions, use the current taxable subtotal plus any taxable adjustments
            $baseTaxableSubtotal = $currentTaxableSubtotal->plus($taxableAdjustments);

            if ($condition->target === ConditionTarget::SUBTOTAL) {
                // Apply condition to the subtotal
                if ($condition->type === ConditionType::PERCENTAGE) {
                    $adjustment = $condition->calculate($baseSubtotal);
                } else {
                    $adjustment = $condition->calculate();
                }

                $total = $total->plus($adjustment);

                // If this condition is taxable and affects the subtotal
                if ($condition->taxable) {
                    // Track the taxable portion of this adjustment
                    if ($originalTaxableSubtotal->toFloat() > 0 && $originalSubtotal->toFloat() > 0) {
                        $taxableRatio = $originalTaxableSubtotal->toFloat() / $originalSubtotal->toFloat();
                        $taxableAdjustment = $adjustment->multiplyBy($taxableRatio, RoundingMode::HALF_UP);
                        $taxableAdjustments = $taxableAdjustments->plus($taxableAdjustment);
                    } else {
                        // If all items are taxable or no taxable items, use full adjustment
                        $taxableAdjustments = $taxableAdjustments->plus($adjustment);
                    }
                }

                // Update the current taxable subtotal proportionally for all subtotal conditions
                if ($originalTaxableSubtotal->toFloat() > 0 && $originalSubtotal->toFloat() > 0) {
                    $taxableRatio = $originalTaxableSubtotal->toFloat() / $originalSubtotal->toFloat();
                    $taxableAdjustment = $adjustment->multiplyBy($taxableRatio, RoundingMode::HALF_UP);
                    $currentTaxableSubtotal = $currentTaxableSubtotal->plus($taxableAdjustment);
                }
            } elseif ($condition->target === ConditionTarget::TAXABLE) {
                // For tax conditions, use the current taxable subtotal that includes effects of other conditions
                // plus any taxable adjustments from subtotal conditions
                if ($condition->type === ConditionType::PERCENTAGE) {
                    $adjustment = $condition->calculate($baseTaxableSubtotal);
                } elseif ($condition->type === ConditionType::FIXED) {
                    $adjustment = $condition->calculate();
                } else {
                    $adjustment = $condition->calculate();
                }
                $total = $total->plus($adjustment);
            }
        });

        // Ensure total is never negative
        if ($total->toFloat() < 0) {
            return new Price(0);
        }

        return $total;

    }

    /**
     * @throws PriceException
     * @throws MoneyMismatchException
     * @throws MathException
     * @throws UnknownCurrencyException
     */
    public function getRawCartData(): array
    {
        return [
            'items'      => $this->items,
            'subtotal'   => $this->subtotal(),
            'total'      => $this->total(),
            'count'      => $this->count(),
            'conditions' => $this->conditions
                ->map(fn ($condition) => $condition),
        ];
    }

    /**
     * Check if the item already exists in the cart and update it accordingly.
     *
     * @param  array  $item  The item to check and update
     * @return array The updated item array
     */
    private function updateExistingItem(array $item): array
    {
        $itemId = $item['id'];

        if ($this->items->has($itemId)) {
            $existingItem = $this->items->get($itemId);

            $item['quantity'] = ($item['quantity'] ?? 1) + $existingItem->quantity;

            $item['taxable'] ??= $existingItem->taxable;

            // If existing item has attributes, merge with new ones if provided
            if (isset($item['attributes'])) {
                $mergedAttributes = array_merge($existingItem->attributes->toArray(), $item['attributes']);
                $item['attributes'] = $mergedAttributes;
            } else {
                $item['attributes'] = $existingItem->attributes;
            }

            // If existing item has conditions and new ones aren't provided, keep existing
            if ($existingItem->conditions->isNotEmpty() && ! isset($item['conditions'])) {
                $item['conditions'] = $existingItem->conditions;
            }
        }

        return $item;
    }

    /**
     * Persist the cart data to storage.
     */
    private function persist(): void
    {
        $this->storage->put([
            'items'      => $this->items,
            'conditions' => $this->conditions,
        ]);
    }
}
