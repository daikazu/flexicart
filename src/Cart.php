<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Conditions\Rules\RuleInterface;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Events\CartCleared;
use Daikazu\Flexicart\Events\CartMerged;
use Daikazu\Flexicart\Events\CartReset;
use Daikazu\Flexicart\Events\ConditionAdded;
use Daikazu\Flexicart\Events\ConditionRemoved;
use Daikazu\Flexicart\Events\ConditionsCleared;
use Daikazu\Flexicart\Events\ItemAdded;
use Daikazu\Flexicart\Events\ItemConditionAdded;
use Daikazu\Flexicart\Events\ItemConditionRemoved;
use Daikazu\Flexicart\Events\ItemQuantityUpdated;
use Daikazu\Flexicart\Events\ItemRemoved;
use Daikazu\Flexicart\Events\ItemUpdated;
use Daikazu\Flexicart\Events\RuleAdded;
use Daikazu\Flexicart\Events\RuleRemoved;
use Daikazu\Flexicart\Events\RulesCleared;
use Daikazu\Flexicart\Exceptions\CartException;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Strategies\MergeStrategyFactory;
use Daikazu\Flexicart\Strategies\MergeStrategyInterface;
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
     * The cart rules (advanced conditions with cart context).
     *
     * @var Collection<string, RuleInterface>
     */
    public Collection $rules;

    /**
     * Create a new cart instance.
     */
    public function __construct(/**
     * The storage implementation.
     */
        private readonly StorageInterface $storage
    ) {
        $data = $this->storage->get();
        $itemsData = $data['items'] ?? [];
        $conditionsData = $data['conditions'] ?? [];
        $rulesData = $data['rules'] ?? [];

        /** @var Collection<string, CartItem> $items */
        $items = collect();
        if ($itemsData instanceof Collection) {
            $items = $itemsData;
        } elseif (is_array($itemsData)) {
            foreach ($itemsData as $itemId => $item) {
                if ($item instanceof CartItem) {
                    $items->put((string) $itemId, $item);
                } elseif (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $items->put((string) $itemId, new CartItem($item));
                }
            }
        }
        $this->items = $items;

        /** @var Collection<string, ConditionInterface> $conditions */
        $conditions = $conditionsData instanceof Collection ? $conditionsData : collect(is_array($conditionsData) ? $conditionsData : []);
        $this->conditions = $conditions;

        /** @var Collection<string, RuleInterface> $rules */
        $rules = $rulesData instanceof Collection ? $rulesData : collect(is_array($rulesData) ? $rulesData : []);
        $this->rules = $rules;
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
        /** @var Collection<string, CartItem> $items */
        $items = collect();
        $itemsData = $cartData['items'] ?? [];
        if (is_array($itemsData)) {
            foreach ($itemsData as $itemId => $item) {
                if ($item instanceof CartItem) {
                    $items->put((string) $itemId, $item);
                } elseif (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $items->put((string) $itemId, new CartItem($item));
                }
            }
        }
        $cart->items = $items;

        $conditionsData = $cartData['conditions'] ?? [];
        /** @var Collection<string, ConditionInterface> $conditions */
        $conditions = collect(is_array($conditionsData) ? $conditionsData : []);
        $cart->conditions = $conditions;

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
     * @param  array<string, mixed>|CartItem  $item
     *
     * @throws CartException
     * @throws PriceException
     */
    public function addItem(array | CartItem $item): self
    {
        if ($item instanceof CartItem) {
            $existingItem = $this->items->get((string) $item->id);
            $oldQuantity = $existingItem?->quantity;

            $this->items->put((string) $item->id, $item);
            $this->persist();

            if ($oldQuantity !== null) {
                $this->dispatchEvent(new ItemQuantityUpdated($this->id(), $item, $oldQuantity, $item->quantity));
            } else {
                $this->dispatchEvent(new ItemAdded($this->id(), $item));
            }
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
            $itemIdString = is_string($itemId) || is_int($itemId) ? (string) $itemId : '';

            $existingItem = $this->items->get($itemIdString);
            $oldQuantity = $existingItem?->quantity;

            $item = $this->updateExistingItem($item);
            $cartItem = CartItem::make($item);

            $this->items->put($itemIdString, $cartItem);
            $this->persist();

            if ($oldQuantity !== null) {
                $this->dispatchEvent(new ItemQuantityUpdated($this->id(), $cartItem, $oldQuantity, $cartItem->quantity));
            } else {
                $this->dispatchEvent(new ItemAdded($this->id(), $cartItem));
            }
        }

        return $this;
    }

    /**
     * Update an item in the cart.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws PriceException
     */
    public function updateItem(string $itemId, array $attributes): self
    {
        if (! $this->items->has($itemId)) {
            return $this; // Return early if item doesn't exist
        }

        $existingItem = $this->items->get($itemId);

        if ($existingItem === null) {
            return $this; // Extra safety check
        }

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
            $attributesValue = $attributes['attributes'];
            if (is_array($attributesValue) || is_object($attributesValue)) {
                $item['attributes'] = fluent($attributesValue);
            }
        }

        // If the existing item has conditions, add them to the new item
        if ($existingItem->conditions->isNotEmpty()) {
            $item['conditions'] = $existingItem->conditions;
        }

        if (isset($attributes['conditions']) && (is_array($attributes['conditions']) || $attributes['conditions'] instanceof Collection)) {
            $item['conditions'] = $existingItem->conditions->merge($attributes['conditions']);
        }

        $cartItem = new CartItem($item);
        $this->items->put($itemId, $cartItem);

        $this->persist();

        $this->dispatchEvent(new ItemUpdated($this->id(), $cartItem, $attributes));

        return $this;
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(string $itemId): self
    {
        $item = $this->items->get($itemId);

        $this->items->forget($itemId);
        $this->persist();

        if ($item !== null) {
            $this->dispatchEvent(new ItemRemoved($this->id(), $item));
        }

        return $this;
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): self
    {
        $clearedItems = $this->items;

        /** @var Collection<string, CartItem> $emptyItems */
        $emptyItems = collect();
        $this->items = $emptyItems;
        $this->persist();

        if ($clearedItems->isNotEmpty()) {
            $this->dispatchEvent(new CartCleared($this->id(), $clearedItems));
        }

        return $this;
    }

    /**
     * Clear all items, conditions, and rules from the cart.
     */
    public function reset(): self
    {
        $clearedItems = $this->items;
        $clearedConditions = $this->conditions;
        $clearedRules = $this->rules;

        /** @var Collection<string, CartItem> $emptyItems */
        $emptyItems = collect();
        $this->items = $emptyItems;

        /** @var Collection<string, ConditionInterface> $emptyConditions */
        $emptyConditions = collect();
        $this->conditions = $emptyConditions;

        /** @var Collection<string, RuleInterface> $emptyRules */
        $emptyRules = collect();
        $this->rules = $emptyRules;

        $this->persist();

        if ($clearedItems->isNotEmpty() || $clearedConditions->isNotEmpty()) {
            $this->dispatchEvent(new CartReset($this->id(), $clearedItems, $clearedConditions));
        }

        if ($clearedRules->isNotEmpty()) {
            $this->dispatchEvent(new RulesCleared($this->id(), $clearedRules));
        }

        return $this;
    }

    /**
     * Get a specific item from the cart.
     */
    public function item(int | string $itemId): ?CartItem
    {
        return $this->items->get((string) $itemId);
    }

    /**
     * Get all items from the cart.
     *
     * @return Collection<string, CartItem>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Get all conditions from the cart.
     *
     * @return Collection<string, ConditionInterface>
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
        return $this->items->sum(fn (CartItem $item): int => $item->quantity);
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

        $this->items->each(function (CartItem $item) use (&$subtotalPrice): void {
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

        $this->items->each(function (CartItem $item) use (&$taxableSubtotal): void {

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
     *
     * @param  array<string, mixed>|ConditionInterface  $condition
     */
    public function addCondition(array | ConditionInterface $condition): self
    {
        // Convert array to ConditionInterface if needed
        if (is_array($condition)) {
            $condition = Condition::make($condition);
        }

        // Check if a condition with the same name already exists
        $existingIndex = $this->conditions->search(fn (ConditionInterface $item): bool => $item->name === $condition->name);
        $replaced = $existingIndex !== false;

        if ($replaced) {
            $this->conditions->put((string) $existingIndex, $condition);
        } else {
            $this->conditions->push($condition);
        }

        $this->persist();

        $this->dispatchEvent(new ConditionAdded($this->id(), $condition, $replaced));

        return $this;
    }

    /**
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
     * Clear all global conditions.
     */
    public function clearConditions(): self
    {
        $clearedConditions = $this->conditions;

        /** @var Collection<string, ConditionInterface> $emptyConditions */
        $emptyConditions = collect();
        $this->conditions = $emptyConditions;

        $this->persist();

        if ($clearedConditions->isNotEmpty()) {
            $this->dispatchEvent(new ConditionsCleared($this->id(), $clearedConditions));
        }

        return $this;
    }

    /**
     * Remove a specific global condition by name.
     */
    public function removeCondition(string $conditionName): self
    {
        $removedCondition = $this->conditions->first(fn (ConditionInterface $condition): bool => $condition->name === $conditionName);

        /** @var Collection<int, ConditionInterface> $filtered */
        $filtered = $this->conditions->reject(fn (ConditionInterface $condition): bool => $condition->name === $conditionName)->values();

        /** @var Collection<string, ConditionInterface> $reindexed */
        $reindexed = $filtered->mapWithKeys(fn (ConditionInterface $condition, int $key): array => [(string) $key => $condition]);

        $this->conditions = $reindexed;

        $this->persist();

        if ($removedCondition !== null) {
            $this->dispatchEvent(new ConditionRemoved($this->id(), $removedCondition));
        }

        return $this;
    }

    /**
     * Add a condition to a specific cart item.
     *
     * @param  int|string  $itemId  The ID of the item
     * @param  Condition  $condition  The condition to add
     */
    public function addItemCondition(int | string $itemId, ConditionInterface $condition): self
    {
        if ($this->items->has((string) $itemId)) {
            $item = $this->items->get((string) $itemId);
            if ($item !== null) {
                $item->addCondition($condition);
                $this->persist();
                $this->dispatchEvent(new ItemConditionAdded($this->id(), $item, $condition));
            }
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
    public function removeItemCondition(int | string $itemId, string $conditionName): static
    {
        if ($this->items->has((string) $itemId)) {
            $item = $this->items->get((string) $itemId);
            if ($item !== null) {
                $item->removeCondition($conditionName);
                $this->persist();
                $this->dispatchEvent(new ItemConditionRemoved($this->id(), $item, $conditionName));
            }
        }

        return $this;
    }

    /**
     * Add a rule to the cart.
     */
    public function addRule(RuleInterface $rule): self
    {
        $replaced = $this->rules->has($rule->getName());
        $this->rules->put($rule->getName(), $rule);
        $this->persist();
        $this->dispatchEvent(new RuleAdded($this->id(), $rule, $replaced));

        return $this;
    }

    /**
     * Get all rules from the cart.
     *
     * @return Collection<string, RuleInterface>
     */
    public function rules(): Collection
    {
        return $this->rules;
    }

    /**
     * Remove a specific rule by name.
     */
    public function removeRule(string $ruleName): self
    {
        $removedRule = $this->rules->get($ruleName);

        $this->rules->forget($ruleName);
        $this->persist();

        if ($removedRule !== null) {
            $this->dispatchEvent(new RuleRemoved($this->id(), $removedRule));
        }

        return $this;
    }

    /**
     * Clear all rules from the cart.
     */
    public function clearRules(): self
    {
        $clearedRules = $this->rules;

        /** @var Collection<string, RuleInterface> $emptyRules */
        $emptyRules = collect();
        $this->rules = $emptyRules;

        $this->persist();

        if ($clearedRules->isNotEmpty()) {
            $this->dispatchEvent(new RulesCleared($this->id(), $clearedRules));
        }

        return $this;
    }

    /**
     * Calculate the cart total (subtotal + cart conditions + rules).
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
        $sortedConditions = $this->conditions->sortBy(function (ConditionInterface $condition): array {
            // Define target priority (lower number = higher priority)

            /** @var array<string, int> */
            $targetPriorities = [
                ConditionTarget::SUBTOTAL->value => 1, // Apply to subtotal first (before taxes)
                ConditionTarget::TAXABLE->value  => 2, // Apply to taxable items
            ];

            $targetValue = $condition->target->value;
            $priority = is_string($targetValue) && isset($targetPriorities[$targetValue]) ? $targetPriorities[$targetValue] : 999;

            return [
                $priority, // Default to end if unknown target
                $condition->taxable ? 2 : 1, // Tax conditions last within each target group
                $condition->order,
                is_int($condition->value) || is_float($condition->value) ? -$condition->value : 0, // Negative for descending order by value
            ];
        });

        $sortedConditions->each(function (ConditionInterface $condition) use (&$total, &$currentTaxableSubtotal, &$taxableAdjustments, $originalSubtotal, $originalTaxableSubtotal, $compoundDiscounts): void {

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

        // Apply rules (advanced conditions with cart context)
        $this->rules->each(function (RuleInterface $rule) use (&$total, $subtotal): void {
            // Set cart context for the rule
            $rule->setCartContext($this->items, $subtotal);

            // Only apply if the rule applies to current cart state
            if ($rule->applies()) {
                $discount = $rule->getDiscount();
                $total = $total->plus($discount);
            }
        });

        // Ensure total is never negative
        if ($total->toFloat() < 0) {
            return new Price(0);
        }

        return $total;

    }

    /**
     * @return array<string, mixed>
     *
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
                ->map(fn (ConditionInterface $condition): ConditionInterface => $condition),
        ];
    }

    /**
     * Merge another cart into this cart.
     *
     * @param  Cart|string  $source  The source cart or cart ID to merge from
     * @param  string|MergeStrategyInterface|null  $strategy  The merge strategy to use (default from config)
     *
     * @throws CartException
     * @throws PriceException
     */
    public function mergeFrom(Cart | string $source, string | MergeStrategyInterface | null $strategy = null): self
    {
        // Resolve source cart
        $sourceCart = $source instanceof Cart ? $source : self::getCartById($source);

        if ($sourceCart === null) {
            throw new CartException('Source cart not found');
        }

        // Don't merge a cart into itself
        if ($sourceCart->id() === $this->id()) {
            return $this;
        }

        // Resolve strategy
        if ($strategy === null) {
            $strategy = MergeStrategyFactory::default();
        } elseif (is_string($strategy)) {
            $strategy = MergeStrategyFactory::make($strategy);
        }

        /** @var Collection<string, CartItem> $mergedItems */
        $mergedItems = collect();

        // Merge items
        foreach ($sourceCart->items() as $sourceItem) {
            $itemId = (string) $sourceItem->id;
            $existingItem = $this->items->get($itemId);

            if ($existingItem !== null) {
                // Item exists in both carts - merge using strategy
                $mergedData = $strategy->mergeItem($existingItem, $sourceItem);
            } else {
                // New item - add it
                $mergedData = $strategy->handleNewItem($sourceItem);
            }

            $cartItem = new CartItem($mergedData);
            $this->items->put($itemId, $cartItem);
            $mergedItems->put($itemId, $cartItem);
        }

        // Merge conditions using strategy
        $this->conditions = $strategy->mergeConditions($this->conditions, $sourceCart->conditions());

        $this->persist();

        // Dispatch event
        $this->dispatchEvent(new CartMerged(
            $this->id(),
            $sourceCart->id(),
            $mergedItems,
            $strategy->name()
        ));

        // Delete source cart if configured
        if (config('flexicart.merge.delete_source', true)) {
            $sourceCart->reset();
        }

        return $this;
    }

    /**
     * Check if the item already exists in the cart and update it accordingly.
     *
     * @param  array<string, mixed>  $item  The item to check and update
     * @return array<string, mixed> The updated item array
     */
    private function updateExistingItem(array $item): array
    {
        $itemId = $item['id'];
        $itemIdString = is_string($itemId) || is_int($itemId) ? (string) $itemId : '';

        if ($this->items->has($itemIdString)) {
            $existingItem = $this->items->get($itemIdString);

            if ($existingItem === null) {
                return $item;
            }

            $currentQuantity = $item['quantity'] ?? 1;
            $item['quantity'] = (is_int($currentQuantity) || is_float($currentQuantity) ? (int) $currentQuantity : 1) + $existingItem->quantity;

            $item['taxable'] ??= $existingItem->taxable;

            // If existing item has attributes, merge with new ones if provided
            if (isset($item['attributes']) && is_array($item['attributes'])) {
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
            'rules'      => $this->rules,
        ]);
    }

    /**
     * Dispatch an event if events are enabled.
     */
    private function dispatchEvent(object $event): void
    {
        if (config('flexicart.events.enabled', true)) {
            event($event);
        }
    }
}
