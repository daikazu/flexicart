<?php

declare(strict_types=1);

use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)
    ->in(__DIR__);

// Mock implementation of StorageInterface for testing
class MockStorage implements StorageInterface
{
    private array $data = [
        'items'      => [],
        'conditions' => [],
    ];

    private string $cartId = 'mock-cart-id';

    public function get(): array
    {
        return $this->data;
    }

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
        ];
    }

    public function getCartId(): string
    {
        return $this->cartId;
    }

    public function getCartById(string $cartId): ?array
    {
        // Only return data if the cart ID matches
        if ($cartId === $this->cartId) {
            return $this->data;
        }

        return null;
    }
}
