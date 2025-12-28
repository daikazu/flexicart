# Cart Merging

FlexiCart provides a flexible cart merging system that allows you to merge one cart into another. This is particularly useful for merging guest carts with user carts when a user logs in.

## Basic Usage

```php
use Daikazu\Flexicart\Cart;

// Merge source cart into target cart
$targetCart->mergeFrom($sourceCart);

// Or merge by cart ID (requires database storage)
$targetCart->mergeFrom('source-cart-id');
```

## Merge Strategies

FlexiCart supports four built-in merge strategies that control how items and conditions are merged:

### Sum Strategy (Default)

Adds quantities together when the same item exists in both carts. Source attributes win, and conditions are combined.

```php
$targetCart->mergeFrom($sourceCart, 'sum');
```

| Aspect | Behavior |
|--------|----------|
| Quantity | Target + Source |
| Attributes | Source wins |
| Item Conditions | Combined (source overrides same-named) |
| Cart Conditions | Combined (source overrides same-named) |

**Example:**
```php
// Target has: Widget (qty: 2)
// Source has: Widget (qty: 3)
// Result: Widget (qty: 5)
```

### Replace Strategy

Source completely replaces target. Useful when you want the source cart to take precedence.

```php
$targetCart->mergeFrom($sourceCart, 'replace');
```

| Aspect | Behavior |
|--------|----------|
| Quantity | Source |
| Attributes | Source |
| Item Conditions | Source |
| Cart Conditions | Source |

### Max Strategy

Keeps the highest quantity. Source attributes win, and conditions are combined.

```php
$targetCart->mergeFrom($sourceCart, 'max');
```

| Aspect | Behavior |
|--------|----------|
| Quantity | Max of Target and Source |
| Attributes | Source wins |
| Item Conditions | Combined |
| Cart Conditions | Combined |

**Example:**
```php
// Target has: Widget (qty: 5)
// Source has: Widget (qty: 3)
// Result: Widget (qty: 5)
```

### Keep Target Strategy

Keeps target values for existing items. Only adds items that don't exist in the target.

```php
$targetCart->mergeFrom($sourceCart, 'keep_target');
```

| Aspect | Behavior |
|--------|----------|
| Quantity | Target (for existing items) |
| Attributes | Target (for existing items) |
| Item Conditions | Target |
| Cart Conditions | Target |

## Configuration

Configure default merge behavior in `config/flexicart.php`:

```php
'merge' => [
    // Default merge strategy: 'sum', 'replace', 'max', 'keep_target'
    'default_strategy' => env('CART_MERGE_STRATEGY', 'sum'),

    // Whether to clear the source cart after merging
    'delete_source' => env('CART_MERGE_DELETE_SOURCE', true),
],
```

## Using Strategy Objects

You can also pass a strategy object instead of a string:

```php
use Daikazu\Flexicart\Strategies\MaxMergeStrategy;

$targetCart->mergeFrom($sourceCart, new MaxMergeStrategy());
```

## Custom Merge Strategies

You can create custom merge strategies by implementing `MergeStrategyInterface`:

```php
namespace App\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Strategies\MergeStrategyInterface;
use Illuminate\Support\Collection;

class CustomMergeStrategy implements MergeStrategyInterface
{
    public function name(): string
    {
        return 'custom';
    }

    public function mergeItem(CartItem $targetItem, CartItem $sourceItem): array
    {
        // Return merged item data array
        return [
            'id' => $targetItem->id,
            'name' => $sourceItem->name,
            'price' => $sourceItem->unitPrice(),
            'quantity' => $targetItem->quantity + $sourceItem->quantity,
            'taxable' => $sourceItem->taxable,
            'attributes' => $sourceItem->attributes->toArray(),
        ];
    }

    public function handleNewItem(CartItem $sourceItem): array
    {
        return [
            'id' => $sourceItem->id,
            'name' => $sourceItem->name,
            'price' => $sourceItem->unitPrice(),
            'quantity' => $sourceItem->quantity,
            'taxable' => $sourceItem->taxable,
            'attributes' => $sourceItem->attributes->toArray(),
        ];
    }

    public function mergeConditions(Collection $targetConditions, Collection $sourceConditions): Collection
    {
        // Return merged conditions collection
        return $targetConditions->merge($sourceConditions);
    }
}
```

Register your custom strategy:

```php
use Daikazu\Flexicart\Strategies\MergeStrategyFactory;

MergeStrategyFactory::register('custom', CustomMergeStrategy::class);

// Now you can use it
$targetCart->mergeFrom($sourceCart, 'custom');
```

## CartMerged Event

When carts are merged, a `CartMerged` event is dispatched:

```php
use Daikazu\Flexicart\Events\CartMerged;

Event::listen(CartMerged::class, function (CartMerged $event) {
    Log::info("Cart merged", [
        'target_cart_id' => $event->cartId,
        'source_cart_id' => $event->sourceCartId,
        'merged_items_count' => $event->mergedItems->count(),
        'strategy' => $event->strategy,
    ]);
});
```

### Event Properties

| Property | Type | Description |
|----------|------|-------------|
| `cartId` | string | The target cart ID |
| `sourceCartId` | string | The source cart ID that was merged |
| `mergedItems` | Collection | Items that were merged/added |
| `strategy` | string | The merge strategy name used |
| `occurredAt` | DateTimeImmutable | When the merge occurred |

## Use Cases

### Guest to User Cart Merge

```php
// When user logs in
public function handleLogin(Request $request)
{
    $user = Auth::user();
    $guestCartId = session('guest_cart_id');

    if ($guestCartId) {
        $userCart = Cart::getCartById($user->cart_id);
        $userCart->mergeFrom($guestCartId, 'sum');

        session()->forget('guest_cart_id');
    }
}
```

### Wishlist to Cart

```php
// Convert wishlist to cart
$cart->mergeFrom($wishlistCart, 'keep_target');
```

### Cart Recovery

```php
// Restore abandoned cart
$currentCart->mergeFrom($abandonedCart, 'max');
```

## Strategy Comparison

| Strategy | Best For |
|----------|----------|
| `sum` | Combining carts where you want all quantities |
| `replace` | Source should override everything |
| `max` | Keeping the larger quantity |
| `keep_target` | Preserving target, only adding new items |

## Error Handling

```php
use Daikazu\Flexicart\Exceptions\CartException;

try {
    $targetCart->mergeFrom('invalid-cart-id');
} catch (CartException $e) {
    // Source cart not found
    Log::error($e->getMessage());
}
```

## Available Strategies

Get a list of available merge strategies:

```php
use Daikazu\Flexicart\Strategies\MergeStrategyFactory;

$strategies = MergeStrategyFactory::available();
// ['sum', 'replace', 'max', 'keep_target']
```
