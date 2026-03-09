# Add Item Behavior Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow configuring whether adding a duplicate item to the cart updates the existing item (sum quantities) or adds it as a separate line item.

**Architecture:** New `AddItemBehavior` enum with `Update` and `New` cases. Config default in `flexicart.php`, per-call override on `addItem()`. When `New` is used and the ID exists, a `:{n}` suffix is appended to create a unique ID. Commerce client `addToCart` passes the behavior through.

**Tech Stack:** PHP 8.4, Pest 4, Laravel 12

---

### Task 1: Create AddItemBehavior Enum

**Files:**
- Create: `src/Enums/AddItemBehavior.php`
- Test: `tests/AddItemBehaviorTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Enums\AddItemBehavior;

test('AddItemBehavior enum has expected cases', function (): void {
    expect(AddItemBehavior::cases())->toHaveCount(2)
        ->and(AddItemBehavior::Update->value)->toBe('update')
        ->and(AddItemBehavior::New->value)->toBe('new');
});

test('AddItemBehavior can be created from string', function (): void {
    expect(AddItemBehavior::from('update'))->toBe(AddItemBehavior::Update)
        ->and(AddItemBehavior::from('new'))->toBe(AddItemBehavior::New);
});
```

**Step 2: Run test to verify it fails**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest tests/AddItemBehaviorTest.php`
Expected: FAIL — class not found

**Step 3: Create the enum**

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Enums;

enum AddItemBehavior: string
{
    case Update = 'update';
    case New = 'new';
}
```

**Step 4: Run test to verify it passes**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest tests/AddItemBehaviorTest.php`
Expected: PASS

**Step 5: Commit**

```
git add src/Enums/AddItemBehavior.php tests/AddItemBehaviorTest.php
git commit -m "feat: add AddItemBehavior enum"
```

---

### Task 2: Add Config Key

**Files:**
- Modify: `config/flexicart.php`

**Step 1: Add the config entry**

Add after the `compound_discounts` block (after line 81), before the cleanup block:

```php
    /*
    |--------------------------------------------------------------------------
    | Add Item Behavior
    |--------------------------------------------------------------------------
    |
    | Controls what happens when adding an item that already exists in the cart.
    |
    |   - 'update': Sum quantities, merge attributes (default)
    |   - 'new': Always add as a separate line item with a unique ID
    |
    */
    'add_item_behavior' => env('CART_ADD_ITEM_BEHAVIOR', 'update'),
```

**Step 2: Commit**

```
git add config/flexicart.php
git commit -m "feat: add add_item_behavior config key"
```

---

### Task 3: Update CartInterface

**Files:**
- Modify: `src/Contracts/CartInterface.php`

**Step 1: Update the addItem signature**

Add import at top:
```php
use Daikazu\Flexicart\Enums\AddItemBehavior;
```

Change the `addItem` method signature from:
```php
public function addItem(array | CartItem $item): self;
```
To:
```php
public function addItem(array | CartItem $item, ?AddItemBehavior $behavior = null): self;
```

**Step 2: Commit**

```
git add src/Contracts/CartInterface.php
git commit -m "feat: add behavior param to CartInterface::addItem"
```

---

### Task 4: Update Cart::addItem with New Behavior

**Files:**
- Modify: `src/Cart.php`
- Test: `tests/CartTest.php`

**Step 1: Write failing tests**

Add these tests inside the existing `describe('Adding Items', ...)` block in `tests/CartTest.php`, after the existing "merges attributes when adding existing item" test (after line 174):

```php
            test('adds item as new line when behavior is New and ID exists', function (): void {
                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::New);

                expect($this->cart->items())->toHaveCount(2)
                    ->and($this->cart->item('product1'))->not->toBeNull()
                    ->and($this->cart->item('product1:1'))->not->toBeNull()
                    ->and($this->cart->item('product1')->quantity)->toBe(1)
                    ->and($this->cart->item('product1:1')->quantity)->toBe(1);
            });

            test('increments suffix for multiple new line items', function (): void {
                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::New);
                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::New);

                expect($this->cart->items())->toHaveCount(3)
                    ->and($this->cart->item('product1'))->not->toBeNull()
                    ->and($this->cart->item('product1:1'))->not->toBeNull()
                    ->and($this->cart->item('product1:2'))->not->toBeNull();
            });

            test('adds item normally when behavior is New and ID does not exist', function (): void {
                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::New);

                expect($this->cart->items())->toHaveCount(1)
                    ->and($this->cart->item('product1'))->not->toBeNull();
            });

            test('uses config default when behavior is null', function (): void {
                config(['flexicart.add_item_behavior' => 'new']);

                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item);

                expect($this->cart->items())->toHaveCount(2);
            });

            test('per-call behavior overrides config default', function (): void {
                config(['flexicart.add_item_behavior' => 'new']);

                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::Update);

                expect($this->cart->items())->toHaveCount(1)
                    ->and($this->cart->item('product1')->quantity)->toBe(2);
            });

            test('dispatches ItemAdded event for new line item', function (): void {
                Event::fake();

                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item, \Daikazu\Flexicart\Enums\AddItemBehavior::New);

                Event::assertDispatched(\Daikazu\Flexicart\Events\ItemAdded::class, 2);
            });
```

**Step 2: Run tests to verify they fail**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest tests/CartTest.php --filter="behavior is New|new line|config default when behavior|per-call behavior|ItemAdded event for new"`
Expected: FAIL

**Step 3: Implement Cart::addItem changes**

In `src/Cart.php`:

Add import at top:
```php
use Daikazu\Flexicart\Enums\AddItemBehavior;
```

Replace the `addItem` method (lines 163-210) with:

```php
    public function addItem(array | CartItem $item, ?AddItemBehavior $behavior = null): self
    {
        $behavior ??= AddItemBehavior::tryFrom((string) config('flexicart.add_item_behavior', 'update'))
            ?? AddItemBehavior::Update;

        if ($item instanceof CartItem) {
            $itemId = (string) $item->id;

            if ($behavior === AddItemBehavior::New && $this->items->has($itemId)) {
                $itemId = $this->nextAvailableId($itemId);
                $item = new CartItem([
                    'id'         => $itemId,
                    'name'       => $item->name,
                    'price'      => $item->unitPrice(),
                    'quantity'   => $item->quantity,
                    'taxable'    => $item->taxable,
                    'attributes' => $item->attributes,
                    'conditions' => $item->conditions,
                ]);
            }

            $existingItem = $this->items->get($itemId);
            $oldQuantity = $existingItem?->quantity;

            $this->items->put($itemId, $item);
            $this->persist();

            if ($oldQuantity !== null && $behavior === AddItemBehavior::Update) {
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

            if ($behavior === AddItemBehavior::New && $this->items->has($itemIdString)) {
                $itemIdString = $this->nextAvailableId($itemIdString);
                $item['id'] = $itemIdString;
            }

            $existingItem = $this->items->get($itemIdString);
            $oldQuantity = $existingItem?->quantity;

            if ($behavior === AddItemBehavior::Update) {
                $item = $this->updateExistingItem($item);
            }

            $cartItem = CartItem::make($item);

            $this->items->put($itemIdString, $cartItem);
            $this->persist();

            if ($oldQuantity !== null && $behavior === AddItemBehavior::Update) {
                $this->dispatchEvent(new ItemQuantityUpdated($this->id(), $cartItem, $oldQuantity, $cartItem->quantity));
            } else {
                $this->dispatchEvent(new ItemAdded($this->id(), $cartItem));
            }
        }

        return $this;
    }
```

Add the `nextAvailableId` private method after the `updateExistingItem` method (after line 881):

```php
    /**
     * Find the next available suffixed ID for a new line item.
     */
    private function nextAvailableId(string $baseId): string
    {
        $suffix = 1;

        while ($this->items->has("{$baseId}:{$suffix}")) {
            $suffix++;
        }

        return "{$baseId}:{$suffix}";
    }
```

**Step 4: Run tests to verify they pass**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest tests/CartTest.php`
Expected: ALL PASS

**Step 5: Commit**

```
git add src/Cart.php tests/CartTest.php
git commit -m "feat: add item behavior support to Cart::addItem"
```

---

### Task 5: Update CommerceClientInterface and Drivers

**Files:**
- Modify: `src/Contracts/CommerceClientInterface.php`
- Modify: `src/Commerce/LocalCommerceDriver.php`
- Modify: `src/Commerce/CommerceClient.php`

**Step 1: Update CommerceClientInterface**

Add import:
```php
use Daikazu\Flexicart\Enums\AddItemBehavior;
```

Change `addToCart` signature from:
```php
public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem;
```
To:
```php
public function addToCart(string $slug, array $config, ?CartInterface $cart = null, ?AddItemBehavior $behavior = null): CartItem;
```

**Step 2: Update LocalCommerceDriver::addToCart**

Change method signature and pass behavior through (lines 204-213):

```php
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null, ?AddItemBehavior $behavior = null): CartItem
    {
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray(), $behavior);

        return $cart->item($data->id)
            ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
    }
```

Add import at top:
```php
use Daikazu\Flexicart\Enums\AddItemBehavior;
```

**Step 3: Update CommerceClient::addToCart**

Change method signature and pass behavior through (lines 134-143):

```php
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null, ?AddItemBehavior $behavior = null): CartItem
    {
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray(), $behavior);

        return $cart->item($data->id)
            ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
    }
```

Add import at top:
```php
use Daikazu\Flexicart\Enums\AddItemBehavior;
```

**Note:** When behavior is `New` and the item gets a suffixed ID, the `$cart->item($data->id)` lookup will fail because the ID changed. This needs a fix — the `addToCart` method should return the last added item instead. Update both drivers:

```php
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null, ?AddItemBehavior $behavior = null): CartItem
    {
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray(), $behavior);

        // When behavior is New, the ID may have been suffixed — find by base ID or suffixed ID
        $item = $cart->item($data->id);
        if ($item === null && $behavior === AddItemBehavior::New) {
            $item = $cart->items()->last();
        }

        return $item
            ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
    }
```

Use this version for both `LocalCommerceDriver` and `CommerceClient`.

**Step 4: Run full test suite**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest`
Expected: ALL PASS

**Step 5: Commit**

```
git add src/Contracts/CommerceClientInterface.php src/Commerce/LocalCommerceDriver.php src/Commerce/CommerceClient.php
git commit -m "feat: pass AddItemBehavior through commerce client addToCart"
```

---

### Task 6: Run Pint and Final Verification

**Step 1: Run Pint**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pint --dirty --format agent`

**Step 2: Fix any issues and commit**

**Step 3: Run full test suite**

Run: `cd /Users/mikewall/Code/flexicart && vendor/bin/pest`
Expected: ALL PASS
