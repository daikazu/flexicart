<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Contracts;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Collection;

interface CartInterface
{
    /**
     * Add an item to the cart
     *
     * @param  array<string, mixed>|CartItem  $item
     * @return $this
     */
    public function addItem(array | CartItem $item): self;

    /**
     * Get the cart contents
     *
     * @return Collection<string, CartItem>
     */
    public function items(): Collection;

    /**
     * Update an item in the cart
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function updateItem(string $itemId, array $attributes): self;

    /**
     * Remove an item from the cart
     *
     * @return $this
     */
    public function removeItem(string $itemId): self;

    /**
     * Clear all items from the cart
     *
     * @return $this
     */
    public function clear(): self;

    /**
     * Get a specific item from the cart
     */
    public function item(string $itemId): ?CartItem;

    /**
     * Get the cart subtotal (sum of all item subtotals)
     */
    public function subtotal(): Price;

    /**
     * Get the cart total (subtotal + global conditions)
     */
    public function total(): Price;

    /**
     * Get the total number of items in the cart
     */
    public function count(): int;

    /**
     * Get the total number of unique items in the cart
     */
    public function uniqueCount(): int;

    /**
     * Check if the cart is empty
     */
    public function isEmpty(): bool;

    public function cart(): self;

    public function id(): string;
}
