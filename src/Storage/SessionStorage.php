<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Storage;

use Daikazu\Flexicart\Contracts\StorageInterface;
use Illuminate\Session\SessionManager;

final readonly class SessionStorage implements StorageInterface
{
    private string $key;

    /**
     * Create a new session storage instance.
     */
    public function __construct(
        private SessionManager $session,
        ?string $key = null
    ) {
        $keyValue = in_array($key, [null, '', '0'], true) ? config('flexicart.session_key', 'flexible_cart') : $key;
        // Ensure it's a string for type safety
        $this->key = is_string($keyValue) ? $keyValue : 'flexible_cart';
    }

    /**
     * Get the cart ID.
     */
    public function getCartId(): string
    {
        return $this->key;
    }

    /**
     * Get a cart by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getCartById(string $cartId): ?array
    {
        // In session storage, the cart ID is the session key
        // If the requested cart ID doesn't match our session key, return null
        if ($cartId !== $this->key) {
            return null;
        }

        return $this->get();
    }

    /**
     * Get the cart data from the session.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $data = $this->session->get($this->key, []);

        // Ensure $data is an array
        if (! is_array($data)) {
            $data = [];
        }

        // If the data is already in the new format, return it
        // Check for array OR Collection since session deserialization preserves Collection objects
        if (isset($data['items']) && (is_array($data['items']) || $data['items'] instanceof \Illuminate\Support\Collection)) {
            /** @var array<string, mixed> */
            return $data;
        }

        // Otherwise, convert it to the new format
        /** @var array<string, mixed> */
        return [
            'items'      => $data,
            'conditions' => [],
        ];
    }

    /**
     * Store the cart data in the session.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    public function put(array $cart): array
    {
        // Ensure the cart data is in the new format
        if (! isset($cart['items'])) {
            $cart = [
                'items'      => $cart,
                'conditions' => [],
            ];
        }

        $this->session->put($this->key, $cart);

        return $cart;
    }

    /**
     * Remove the cart data from the session.
     */
    public function flush(): void
    {
        $this->session->forget($this->key);
    }
}
