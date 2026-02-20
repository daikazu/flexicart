<?php

namespace Daikazu\Flexicart\Tests;

use Daikazu\Flexicart\Contracts\StorageInterface;

class MockStorage implements StorageInterface
{
    /** @var array<string, mixed> */
    private array $data = [
        'items'      => [],
        'conditions' => [],
        'rules'      => [],
    ];

    public function __construct(
        private readonly string $cartId = 'mock-cart-id'
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->data;
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    public function put(array $cart): array
    {
        $this->data = $cart;

        return $cart;
    }

    public function flush(): void
    {
        $this->data = [
            'items'      => [],
            'conditions' => [],
            'rules'      => [],
        ];
    }

    public function getCartId(): string
    {
        return $this->cartId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCartById(string $cartId): ?array
    {
        // Only return data if the cart ID matches
        if ($cartId === $this->cartId) {
            return $this->data;
        }

        return null;
    }
}
