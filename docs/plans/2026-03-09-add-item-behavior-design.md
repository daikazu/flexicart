# Add Item Behavior Design

## Problem

When adding an item to the cart that already exists (same ID), the cart always sums quantities and merges attributes. Some use cases need the ability to add the same product as a separate line item instead.

## Solution

Add a configurable `AddItemBehavior` enum that controls what happens when `addItem` encounters a duplicate ID.

### Enum: `AddItemBehavior`

- `Update` — Sum quantities, merge attributes, keep conditions (current behavior)
- `New` — Always add as a separate line item with a unique suffixed ID

### Config

```php
// config/flexicart.php
'add_item_behavior' => 'update',
```

### Cart::addItem Signature

```php
public function addItem(array|CartItem $item, ?AddItemBehavior $behavior = null): self
```

- If `$behavior` is null, resolve from `config('flexicart.add_item_behavior')`
- `Update`: existing `updateExistingItem` logic (no change)
- `New`: if the ID already exists, append `:{n}` suffix to create a unique ID

### ID Suffix Logic

When behavior is `New` and the base ID exists in the cart:

1. Check `{id}:1`, `{id}:2`, etc.
2. Use the first available suffix
3. The suffixed ID becomes the cart item's identity

### Interface Changes

`CartInterface::addItem` updated to accept the optional behavior parameter.

`CommerceClientInterface::addToCart` updated to accept and pass through the behavior parameter.

### Commerce Client Changes

Both `LocalCommerceDriver::addToCart` and `CommerceClient::addToCart` gain an optional `?AddItemBehavior $behavior` parameter, forwarded to `Cart::addItem`.

## Files to Create/Modify

**Create:**
- `src/Enums/AddItemBehavior.php`

**Modify:**
- `config/flexicart.php` — add `add_item_behavior` key
- `src/Contracts/CartInterface.php` — update `addItem` signature
- `src/Cart.php` — update `addItem` to resolve and apply behavior
- `src/Contracts/CommerceClientInterface.php` — update `addToCart` signature
- `src/Commerce/LocalCommerceDriver.php` — pass behavior through
- `src/Commerce/CommerceClient.php` — pass behavior through
- Tests for new behavior
