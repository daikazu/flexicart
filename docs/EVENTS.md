# Cart Events

FlexiCart dispatches Laravel events for various cart actions, enabling you to hook into the cart lifecycle for analytics, inventory management, logging, and other integrations.

## Configuration

Events are enabled by default. You can disable them in your config:

```php
// config/flexicart.php
'events' => [
    'enabled' => env('CART_EVENTS_ENABLED', true),
],
```

## Available Events

All events extend `Daikazu\Flexicart\Events\CartEvent` and include:
- `cartId` - The cart identifier
- `occurredAt` - A `DateTimeImmutable` timestamp

### Item Events

#### `ItemAdded`
Dispatched when a new item is added to the cart.

```php
use Daikazu\Flexicart\Events\ItemAdded;

Event::listen(ItemAdded::class, function (ItemAdded $event) {
    Log::info("Item added: {$event->item->name}");

    // Available properties:
    // $event->cartId
    // $event->item (CartItem instance)
    // $event->occurredAt
});
```

#### `ItemQuantityUpdated`
Dispatched when an existing item's quantity is increased (adding the same item again).

```php
use Daikazu\Flexicart\Events\ItemQuantityUpdated;

Event::listen(ItemQuantityUpdated::class, function (ItemQuantityUpdated $event) {
    Log::info("Quantity changed from {$event->oldQuantity} to {$event->newQuantity}");

    // Available properties:
    // $event->cartId
    // $event->item (CartItem instance)
    // $event->oldQuantity
    // $event->newQuantity
});
```

#### `ItemUpdated`
Dispatched when an item's attributes are updated.

```php
use Daikazu\Flexicart\Events\ItemUpdated;

Event::listen(ItemUpdated::class, function (ItemUpdated $event) {
    Log::info("Item updated", $event->changes);

    // Available properties:
    // $event->cartId
    // $event->item (CartItem instance with new values)
    // $event->changes (array of changed attributes)
});
```

#### `ItemRemoved`
Dispatched when an item is removed from the cart.

```php
use Daikazu\Flexicart\Events\ItemRemoved;

Event::listen(ItemRemoved::class, function (ItemRemoved $event) {
    // Release inventory reservation
    InventoryService::release($event->item->id, $event->item->quantity);

    // Available properties:
    // $event->cartId
    // $event->item (the removed CartItem)
});
```

### Cart Events

#### `CartCleared`
Dispatched when all items are cleared from the cart (conditions remain).

```php
use Daikazu\Flexicart\Events\CartCleared;

Event::listen(CartCleared::class, function (CartCleared $event) {
    foreach ($event->items as $item) {
        InventoryService::release($item->id, $item->quantity);
    }

    // Available properties:
    // $event->cartId
    // $event->items (Collection of cleared CartItems)
});
```

#### `CartReset`
Dispatched when the cart is completely reset (items AND conditions cleared).

```php
use Daikazu\Flexicart\Events\CartReset;

Event::listen(CartReset::class, function (CartReset $event) {
    Log::info("Cart reset: {$event->items->count()} items, {$event->conditions->count()} conditions cleared");

    // Available properties:
    // $event->cartId
    // $event->items (Collection of cleared CartItems)
    // $event->conditions (Collection of cleared conditions)
});
```

### Condition Events

#### `ConditionAdded`
Dispatched when a global condition is added to the cart.

```php
use Daikazu\Flexicart\Events\ConditionAdded;

Event::listen(ConditionAdded::class, function (ConditionAdded $event) {
    if ($event->replaced) {
        Log::info("Condition replaced: {$event->condition->name}");
    } else {
        Log::info("Condition added: {$event->condition->name}");
    }

    // Available properties:
    // $event->cartId
    // $event->condition (ConditionInterface instance)
    // $event->replaced (bool - true if condition with same name was replaced)
});
```

#### `ConditionRemoved`
Dispatched when a global condition is removed.

```php
use Daikazu\Flexicart\Events\ConditionRemoved;

Event::listen(ConditionRemoved::class, function (ConditionRemoved $event) {
    Log::info("Condition removed: {$event->condition->name}");

    // Available properties:
    // $event->cartId
    // $event->condition (the removed condition)
});
```

#### `ConditionsCleared`
Dispatched when all global conditions are cleared.

```php
use Daikazu\Flexicart\Events\ConditionsCleared;

Event::listen(ConditionsCleared::class, function (ConditionsCleared $event) {
    Log::info("All conditions cleared: {$event->conditions->count()} total");

    // Available properties:
    // $event->cartId
    // $event->conditions (Collection of cleared conditions)
});
```

### Item Condition Events

#### `ItemConditionAdded`
Dispatched when a condition is added to a specific item.

```php
use Daikazu\Flexicart\Events\ItemConditionAdded;

Event::listen(ItemConditionAdded::class, function (ItemConditionAdded $event) {
    Log::info("Condition '{$event->condition->name}' added to item '{$event->item->name}'");

    // Available properties:
    // $event->cartId
    // $event->item (CartItem instance)
    // $event->condition (ConditionInterface instance)
});
```

#### `ItemConditionRemoved`
Dispatched when a condition is removed from a specific item.

```php
use Daikazu\Flexicart\Events\ItemConditionRemoved;

Event::listen(ItemConditionRemoved::class, function (ItemConditionRemoved $event) {
    Log::info("Condition '{$event->conditionName}' removed from item '{$event->item->name}'");

    // Available properties:
    // $event->cartId
    // $event->item (CartItem instance)
    // $event->conditionName (string)
});
```

## Use Cases

### Inventory Management

```php
use Daikazu\Flexicart\Events\ItemAdded;
use Daikazu\Flexicart\Events\ItemRemoved;
use Daikazu\Flexicart\Events\ItemQuantityUpdated;

// Reserve inventory when items are added
Event::listen(ItemAdded::class, function (ItemAdded $event) {
    InventoryService::reserve($event->item->id, $event->item->quantity);
});

// Update reservation when quantity changes
Event::listen(ItemQuantityUpdated::class, function (ItemQuantityUpdated $event) {
    $difference = $event->newQuantity - $event->oldQuantity;
    if ($difference > 0) {
        InventoryService::reserve($event->item->id, $difference);
    } else {
        InventoryService::release($event->item->id, abs($difference));
    }
});

// Release inventory when items are removed
Event::listen(ItemRemoved::class, function (ItemRemoved $event) {
    InventoryService::release($event->item->id, $event->item->quantity);
});
```

### Analytics Tracking

```php
use Daikazu\Flexicart\Events\ItemAdded;
use Daikazu\Flexicart\Events\CartCleared;

Event::listen(ItemAdded::class, function (ItemAdded $event) {
    Analytics::track('add_to_cart', [
        'item_id' => $event->item->id,
        'item_name' => $event->item->name,
        'price' => $event->item->price->toFloat(),
        'quantity' => $event->item->quantity,
    ]);
});

Event::listen(CartCleared::class, function (CartCleared $event) {
    Analytics::track('cart_abandoned', [
        'cart_id' => $event->cartId,
        'items_count' => $event->items->count(),
    ]);
});
```

### Notifications

```php
use Daikazu\Flexicart\Events\ConditionAdded;

Event::listen(ConditionAdded::class, function (ConditionAdded $event) {
    if (str_contains($event->condition->name, 'promo')) {
        session()->flash('success', "Promo code applied: {$event->condition->formattedValue()}");
    }
});
```

## Queued Listeners

For performance, you can queue event listeners:

```php
namespace App\Listeners;

use Daikazu\Flexicart\Events\ItemAdded;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncInventory implements ShouldQueue
{
    public function handle(ItemAdded $event): void
    {
        // This runs asynchronously
        InventoryService::syncReservation($event->item->id);
    }
}
```

## Event Subscribers

You can also use Laravel's event subscriber pattern:

```php
namespace App\Listeners;

use Daikazu\Flexicart\Events\ItemAdded;
use Daikazu\Flexicart\Events\ItemRemoved;
use Illuminate\Events\Dispatcher;

class CartEventSubscriber
{
    public function handleItemAdded(ItemAdded $event): void
    {
        // Handle item added
    }

    public function handleItemRemoved(ItemRemoved $event): void
    {
        // Handle item removed
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ItemAdded::class => 'handleItemAdded',
            ItemRemoved::class => 'handleItemRemoved',
        ];
    }
}
```

Register in `EventServiceProvider`:

```php
protected $subscribe = [
    \App\Listeners\CartEventSubscriber::class,
];
```
