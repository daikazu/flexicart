<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

abstract class AbstractRule implements RuleInterface
{
    public ConditionType $type = ConditionType::FIXED;

    public ConditionTarget $target = ConditionTarget::SUBTOTAL;

    /**
     * @var Collection<string, CartItem>
     */
    protected Collection $items;

    protected Price $subtotal;

    protected bool $contextSet = false;

    /**
     * @param  array<string, mixed>|Fluent<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly int | float $value = 0,
        public array | Fluent $attributes = [],
        public int $order = 0,
        public bool $taxable = false
    ) {
        $this->attributes = is_array($attributes) ? fluent($attributes) : $attributes;

        /** @var Collection<string, CartItem> $emptyItems */
        $emptyItems = collect();
        $this->items = $emptyItems;

        try {
            $this->subtotal = Price::zero();
        } catch (PriceException) {
            // This should never happen with zero
        }
    }

    /**
     * Get the rule name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  Collection<string, CartItem>  $items
     */
    public function setCartContext(Collection $items, Price $subtotal): self
    {
        $this->items = $items;
        $this->subtotal = $subtotal;
        $this->contextSet = true;

        return $this;
    }

    /**
     * Calculate the price adjustment for this rule.
     * For rules, this returns the discount amount.
     *
     * @throws PriceException
     */
    public function calculate(?Price $price = null): Price
    {
        if (! $this->contextSet) {
            return Price::zero();
        }

        if (! $this->applies()) {
            return Price::zero();
        }

        return $this->getDiscount();
    }

    /**
     * Format the condition value for display.
     */
    public function formattedValue(): string
    {
        if ($this->type === ConditionType::PERCENTAGE) {
            return sprintf('%s%%', $this->value);
        }

        try {
            $price = new Price($this->value);

            return $price->formatted();
        } catch (PriceException) {
            return (string) $this->value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'value'      => $this->value,
            'type'       => $this->type->value,
            'target'     => $this->target->value,
            'attributes' => $this->attributes instanceof Fluent ? $this->attributes->toArray() : $this->attributes,
            'order'      => $this->order,
            'taxable'    => $this->taxable,
        ];
    }

    /**
     * Get total quantity of items in the cart.
     */
    protected function getTotalQuantity(): int
    {
        return $this->items->sum(fn (CartItem $item): int => $item->quantity);
    }

    /**
     * Get quantity of a specific item or items matching a pattern.
     *
     * @param  string|array<string>  $itemIds
     */
    protected function getItemQuantity(string | array $itemIds): int
    {
        $ids = is_array($itemIds) ? $itemIds : [$itemIds];

        return $this->items
            ->filter(fn (CartItem $item): bool => $this->itemMatchesIds($item, $ids))
            ->sum(fn (CartItem $item): int => $item->quantity);
    }

    /**
     * Check if an item matches any of the given IDs (supports wildcards).
     *
     * @param  array<string>  $ids
     */
    protected function itemMatchesIds(CartItem $item, array $ids): bool
    {
        foreach ($ids as $id) {
            if ($this->matchesPattern((string) $item->id, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value matches a pattern (supports * wildcard).
     */
    protected function matchesPattern(string $value, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (! str_contains($pattern, '*')) {
            return $value === $pattern;
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $value);
    }

    /**
     * Get items matching specific IDs (supports wildcards).
     *
     * @param  string|array<string>  $itemIds
     * @return Collection<string, CartItem>
     */
    protected function getMatchingItems(string | array $itemIds): Collection
    {
        $ids = is_array($itemIds) ? $itemIds : [$itemIds];

        return $this->items->filter(fn (CartItem $item): bool => $this->itemMatchesIds($item, $ids));
    }
}
