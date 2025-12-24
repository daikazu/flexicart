<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Contracts;

interface StorageInterface
{
    /**
     * Get the cart data from storage
     *
     * @return array<string, mixed>
     */
    public function get(): array;

    /**
     * Store the cart data
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    public function put(array $cart): array;

    /**
     * Remove all cart data from storage
     */
    public function flush(): void;

    /**
     * Get the cart ID
     */
    public function getCartId(): string;

    /**
     * Get a cart by ID
     *
     * @return array<string, mixed>|null
     */
    public function getCartById(string $cartId): ?array;
}
