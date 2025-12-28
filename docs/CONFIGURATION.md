# Configuration

FlexiCart provides extensive configuration options via the `config/flexicart.php` file.

## Publishing Configuration

```bash
php artisan vendor:publish --tag="flexicart-config"
```

## Storage Configuration

Configure how cart data is stored:

```php
// config/flexicart.php

// Use session storage (default)
'storage' => 'session',

// Use database storage
'storage' => 'database',

// Use custom storage class
'storage_class' => App\Services\CustomCartStorage::class,
```

### Session Key

Customize the session key when using session storage:

```php
'session_key' => 'my_custom_cart_key',
```

### Database Storage

When using database storage, run the migrations first:

```bash
php artisan vendor:publish --tag="flexicart-migrations"
php artisan migrate
```

Update your `.env` file:

```env
CART_STORAGE=database
```

When using database storage, FlexiCart creates the following tables:
- `carts`: Stores cart metadata (ID, user association, timestamps)
- `cart_items`: Stores individual cart items and their properties

### Cart Persistence

With database storage, carts are automatically persisted. You can also manually persist session-based carts:

```php
// Manually persist cart data
Cart::persist();

// Get raw cart data for custom storage
$cartData = Cart::getRawCartData();
```

### Multiple Carts

You can work with multiple carts by specifying cart IDs:

```php
// Get a specific cart
$cart = Cart::getCartById('user_123_cart');

// Switch to a different cart
$guestCart = Cart::getCartById('guest_cart');
```

## Currency and Locale

Set the default currency and locale for price formatting:

```php
'currency' => 'USD', // ISO currency code
'locale' => 'en_US', // Locale for formatting
```

## Custom Models

When using database storage, you can specify custom models:

```php
'cart_model' => App\Models\CustomCart::class,
'cart_item_model' => App\Models\CustomCartItem::class,
```

## Compound Discounts

Control how multiple discounts are calculated:

```php
// Sequential calculation (each discount applies to the result of previous discounts)
'compound_discounts' => true,

// Parallel calculation (all discounts apply to the original price)
'compound_discounts' => false,
```

**Example with $100 subtotal and two 10% discounts:**

| Mode | Calculation | Result |
|------|-------------|--------|
| Sequential (true) | $100 - 10% = $90, then $90 - 10% = $81 | $81.00 |
| Parallel (false) | $100 - 10% - 10% = $80 | $80.00 |

## Cart Cleanup

Configure automatic cleanup of old carts when using database storage:

```php
'cleanup' => [
    'enabled' => true,
    'lifetime' => 60 * 24 * 7, // 1 week in minutes
],
```

## Events

Enable or disable cart lifecycle events:

```php
'events' => [
    'enabled' => env('CART_EVENTS_ENABLED', true),
],
```

See [Events](EVENTS.md) for detailed event documentation.

## Merge Settings

Configure default cart merging behavior:

```php
'merge' => [
    // Default merge strategy: 'sum', 'replace', 'max', 'keep_target'
    'default_strategy' => env('CART_MERGE_STRATEGY', 'sum'),

    // Whether to clear the source cart after merging
    'delete_source' => env('CART_MERGE_DELETE_SOURCE', true),
],
```

See [Merging](MERGING.md) for detailed merge documentation.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    |
    | The storage driver to use for persisting cart data.
    | Options: 'session', 'database'
    |
    */
    'storage' => env('CART_STORAGE', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Custom Storage Class
    |--------------------------------------------------------------------------
    |
    | Optionally specify a custom storage class that implements StorageInterface.
    |
    */
    'storage_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | The session key used to store cart data when using session storage.
    |
    */
    'session_key' => 'flexicart',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for price calculations (ISO 4217 code).
    |
    */
    'currency' => env('CART_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    |
    | The locale used for price formatting.
    |
    */
    'locale' => env('CART_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Compound Discounts
    |--------------------------------------------------------------------------
    |
    | When true, discounts are applied sequentially (compounding).
    | When false, all discounts are calculated on the original price.
    |
    */
    'compound_discounts' => true,

    /*
    |--------------------------------------------------------------------------
    | Custom Models
    |--------------------------------------------------------------------------
    |
    | Override the default Eloquent models when using database storage.
    |
    */
    'cart_model' => null,
    'cart_item_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Cart Cleanup
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of old/abandoned carts (database storage only).
    |
    */
    'cleanup' => [
        'enabled' => true,
        'lifetime' => 60 * 24 * 7, // 1 week in minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable cart event dispatching.
    |
    */
    'events' => [
        'enabled' => env('CART_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Merge Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for cart merging operations.
    |
    */
    'merge' => [
        'default_strategy' => env('CART_MERGE_STRATEGY', 'sum'),
        'delete_source' => env('CART_MERGE_DELETE_SOURCE', true),
    ],
];
```
