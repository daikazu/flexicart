<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Storage;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Models\CartItemModel;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final readonly class DatabaseStorage implements StorageInterface
{
    private int $cartId;

    private ?int $userId;

    public function __construct(private CartModel $cartModel)
    {
        $authId = Auth::check() ? Auth::id() : null;
        $this->userId = is_int($authId) ? $authId : null;

        // Generate a cart ID for guest users or get existing cart for authenticated users
        $this->cartId = $this->initializeCartId();
    }

    /**
     * Get the cart data from the database.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $cartItems = CartItemModel::where('cart_id', $this->cartId)->get();
        $cart = CartModel::find($this->cartId);

        /** @var array<string, mixed> */
        $items = [];
        foreach ($cartItems as $item) {
            $itemConditions = $item->conditions ?? [];

            // Convert item conditions to array if it's a collection
            if ($itemConditions instanceof Collection) {
                $itemConditions = $itemConditions->toArray();
            }

            // Convert Condition objects to arrays
            foreach ($itemConditions as $key => $condition) {
                if ($condition instanceof Condition) {
                    $itemConditions[$key] = $condition->toArray();
                }
            }

            $items[$item->item_id] = [
                'id'         => $item->item_id,
                'name'       => $item->name,
                'price'      => new Price($item->price),
                'quantity'   => $item->quantity,
                'attributes' => $item->attributes,
                'conditions' => $itemConditions,
            ];
        }

        // Get global conditions from the database
        $conditions = $cart->conditions ?? [];

        // Convert conditions to array if it's a collection
        if ($conditions instanceof Collection) {
            $conditions = $conditions->toArray();
        }

        // Convert Condition objects to arrays
        if ($conditions instanceof Condition) {
            $conditions = [$conditions->toArray()];
        } elseif (is_array($conditions)) {
            foreach ($conditions as $key => $condition) {
                if ($condition instanceof Condition) {
                    $conditions[$key] = $condition->toArray();
                }
            }
        }

        return [
            'items'      => $items,
            'conditions' => $conditions,
        ];

    }

    /**
     * Store the cart data in the database.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    public function put(array $cart): array
    {
        // Extract items and global conditions
        $items = $cart['items'] ?? [];
        $conditions = $cart['conditions'] ?? [];

        // Convert items to array if it's a Collection
        if ($items instanceof Collection) {
            $items = $items->toArray();
        }

        // Convert conditions to array if it's a Collection
        if ($conditions instanceof Collection) {
            $conditions = $conditions->toArray();
        }

        // First, remove all items that are no longer in the cart
        if (is_array($items)) {
            $itemIds = array_keys($items);
            CartItemModel::where('cart_id', $this->cartId)
                ->whereNotIn('item_id', $itemIds)
                ->delete();
        }

        // Then, update or create items
        if (is_array($items)) {
            foreach ($items as $itemId => $item) {
                // Check if $item is a CartItem object or an array
                if ($item instanceof CartItem) {
                    $name = $item->name;
                    $price = $item->unitPrice();
                    $quantity = $item->quantity;
                    $attributes = $item->attributes;
                    $itemConditions = $item->conditions;
                    // Convert Condition objects to arrays
                    $conditionsArray = [];
                    foreach ($itemConditions as $condition) {
                        $conditionsArray[] = $condition instanceof Condition ? $condition->toArray() : $condition;
                    }
                    $itemConditions = collect($conditionsArray);
                } elseif (is_array($item)) {
                    $name = is_string($item['name'] ?? null) ? $item['name'] : '';
                    $price = $item['price'] ?? 0;
                    $quantity = is_int($item['quantity'] ?? null) ? $item['quantity'] : 1;
                    $attributesData = $item['attributes'] ?? [];
                    $attributes = collect(is_array($attributesData) ? $attributesData : []);
                    $conditionsSource = $item['conditions'] ?? [];
                    $itemConditions = collect(is_array($conditionsSource) ? $conditionsSource : []);

                    // Convert Condition objects to arrays
                    $conditionsArray = [];
                    foreach ($itemConditions as $condition) {
                        $conditionsArray[] = $condition instanceof Condition ? $condition->toArray() : $condition;
                    }
                    $itemConditions = collect($conditionsArray);
                } else {
                    continue;
                }

                CartItemModel::updateOrCreate(
                    [
                        'cart_id' => $this->cartId,
                        'item_id' => $itemId,
                    ],
                    [
                        'name'       => $name,
                        'price'      => $price instanceof Price ? $price->toFloat() : $price,
                        'quantity'   => $quantity,
                        'attributes' => $attributes,
                        'conditions' => $itemConditions,
                    ]
                );
            }
        }

        // Store global conditions in the database
        $cartModel = CartModel::find($this->cartId);

        // Convert Condition objects to arrays and prepare Collection
        if (is_array($conditions)) {
            $conditionsArray = [];
            foreach ($conditions as $condition) {
                $conditionsArray[] = $condition instanceof Condition ? $condition->toArray() : $condition;
            }
            $conditionsCollection = collect($conditionsArray);
        } elseif ($conditions instanceof Condition) {
            $conditionsCollection = collect([$conditions->toArray()]);
        } elseif ($conditions instanceof Collection) {
            $conditionsCollection = $conditions;
        } else {
            $conditionsCollection = collect([]);
        }

        if ($cartModel !== null) {
            $cartModel->conditions = $conditionsCollection;
            $cartModel->save();
        }

        return $cart;

    }

    /**
     * Remove all cart data from the database.
     */
    public function flush(): void
    {
        CartItemModel::where('cart_id', $this->cartId)->delete();

        // Clear global conditions from the database
        $cartModel = CartModel::find($this->cartId);
        if ($cartModel !== null) {
            $cartModel->conditions = collect([]);
            $cartModel->save();
        }
    }

    /**
     * Get the cart ID.
     */
    public function getCartId(): string
    {
        return (string) $this->cartId;
    }

    /**
     * Get a cart by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getCartById(string $cartId): ?array
    {
        $cart = CartModel::find((int) $cartId);

        if (! $cart) {
            return null;
        }

        $cartItems = CartItemModel::where('cart_id', $cart->id)->get();

        /** @var array<string, mixed> */
        $items = [];
        foreach ($cartItems as $item) {
            $itemConditions = $item->conditions ?? [];

            // Convert item conditions to array if it's a collection
            if ($itemConditions instanceof Collection) {
                $itemConditions = $itemConditions->toArray();
            }

            // Convert Condition objects to arrays
            foreach ($itemConditions as $key => $condition) {
                if ($condition instanceof Condition) {
                    $itemConditions[$key] = $condition->toArray();
                }
            }

            $items[$item->item_id] = [
                'id'         => $item->item_id,
                'name'       => $item->name,
                'price'      => new Price($item->price),
                'quantity'   => $item->quantity,
                'attributes' => $item->attributes,
                'conditions' => $itemConditions,
            ];
        }

        // Get global conditions from the database
        $conditions = $cart->conditions ?? [];

        // Convert conditions to array if it's a collection
        if ($conditions instanceof Collection) {
            $conditions = $conditions->toArray();
        }

        // Convert Condition objects to arrays
        if ($conditions instanceof Condition) {
            $conditions = [$conditions->toArray()];
        } elseif (is_array($conditions)) {
            foreach ($conditions as $key => $condition) {
                if ($condition instanceof Condition) {
                    $conditions[$key] = $condition->toArray();
                }
            }
        }

        return [
            'items'      => $items,
            'conditions' => $conditions,
        ];
    }

    /**
     * Get or create a cart ID.
     */
    private function initializeCartId(): int
    {
        if ($this->userId !== null && $this->userId !== 0) {
            // For authenticated users, find or create a cart by user ID
            $cart = $this->cartModel->firstOrCreate([
                'user_id' => $this->userId,
            ]);
        } else {
            // For guest users, we'll use a session identifier
            $sessionId = session()->getId();
            $cart = $this->cartModel->firstOrCreate([
                'session_id' => $sessionId,
            ]);
        }

        return $cart->id;
    }
}
