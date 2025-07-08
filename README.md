<a href="https://mikewall.dev">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
  <img alt="Logo for Flexi Cart" src="art/header-light.png">
</picture>
</a>

# FlexiCart
[![PHP Version Require](https://img.shields.io/packagist/php-v/daikazu/flexicart?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B-red?style=flat-square&logo=laravel)](https://laravel.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/flexicart.svg?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/flexicart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/flexicart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/daikazu/flexicart/phpstan.yml?branch=main&label=PHPStan&=flat-square)](https://github.com/daikazu/flexicart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/flexicart.svg?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![GitHub forks](https://img.shields.io/github/forks/daikazu/flexicart?style=flat-square)](https://github.com/daikazu/flexicart/network)
[![GitHub stars](https://img.shields.io/github/stars/daikazu/flexicart?style=flat-square)](https://github.com/daikazu/flexicart/stargazers)

A flexible shopping cart package for Laravel with support for session or database storage, conditional pricing, and custom product attributes.
 
## Table of Contents

- [Features](#features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [Publish Configuration](#publish-configuration)
  - [Database Storage (Optional)](#database-storage-optional)
- [Basic Usage](#basic-usage)
  - [Adding Items to the Cart](#adding-items-to-the-cart)
  - [Updating Items in the Cart](#updating-items-in-the-cart)
  - [Removing Items from the Cart](#removing-items-from-the-cart)
  - [Getting Cart Items and Calculations](#getting-cart-content-and-calculations)
  - [Understanding Conditions](#understanding-conditions)
  - [Adding Conditions](#adding-conditions)
  - [Removing Conditions](#removing-conditions)
  - [Marking Items as Non-Taxable](#marking-items-as-non-taxable)
- [Configuration Options](#configuration-options)
  - [Storage Configuration](#storage-configuration)
  - [Session Key Configuration](#session-key-configuration)
  - [Currency and Locale Configuration](#currency-and-locale-configuration)
  - [Custom Models Configuration](#custom-models-configuration)
- [Working with Prices](#working-with-prices)
  - [Creating Price Objects](#creating-price-objects)
  - [Price Operations](#price-operations)
  - [Formatting and Conversion](#formatting-and-conversion)
- [Displaying the Cart in Blade](#displaying-the-cart-in-blade)
- [Database Storage Notes](#database-storage-notes)
- [Extending the Package](#extending-the-package)
  - [Custom Condition Class](#custom-condition-class)
  - [Custom Storage Method](#custom-storage-method)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Features

- **Flexible Storage**: Use session storage (default) or database storage
- **Cart Item Conditions**: Apply discounts, fees, or any adjustments to items
    - Percentage-based adjustments (e.g., 10% discount)
    - Fixed-amount adjustments (e.g., $5 off, $2 add-on fee)
    - Stack multiple conditions on the same item
- **Custom Product Attributes**: Store any item-specific attributes (color, size, etc.)
- **Global Cart Conditions**: Apply conditions to the cart subtotal or only to taxable items
- **Precise Price Handling**: Uses Brick/Money for accurate currency calculations
- **Taxable Item Support**: Mark specific items as taxable or non-taxable
- **Easy Integration**: Simple API with Laravel Facade
- **Comprehensive Tests**: Unit and feature tests included


## Installation

### Requirements

- PHP 8.3 or higher
- Laravel 11.0 or higher
- Brick/Money package (automatically installed)

### Install the Package

You can install the package via composer:

```bash
composer require daikazu/flexicart
```

### Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --tag="flexicart-config"
```

This will publish the configuration file to `config/flexicart.php`.

### Database Storage (Optional)

If you want to use database storage instead of session storage, you need to run the migrations:

```bash
php artisan vendor:publish --tag="flexicart-migrations"
php artisan migrate
```

Then update your `.env` file:

```env
CART_STORAGE=database
```

## Basic Usage

### Adding Items to the Cart

You can add items to the cart using the `addItem` method. Items can be added as arrays or CartItem objects:

```php
use Daikazu\Flexicart\Facades\Cart;

// Add item as array
Cart::addItem([
    'id' => 1,
    'name' => 'Product Name',
    'price' => 29.99,
    'quantity' => 2,
    'attributes' => [
        'color' => 'red',
        'size' => 'large'
    ]
]);

// Add multiple items at once
Cart::addItem([
    [
        'id' => 2,
        'name' => 'Another Product',
        'price' => 15.50,
        'quantity' => 1
    ],
    [
        'id' => 3,
        'name' => 'Third Product',
        'price' => 45.00,
        'quantity' => 3
    ]
]);
```

### Updating Items in the Cart

Update existing items using the `updateItem` method:

```php
// Update quantity
Cart::updateItem('item_id', ['quantity' => 5]);

// Update attributes
Cart::updateItem('item_id', [
    'attributes' => [
        'color' => 'blue',
        'size' => 'medium'
    ]
]);

// Update multiple properties
Cart::updateItem('item_id', [
    'quantity' => 3,
    'price' => 25.99,
    'attributes' => ['color' => 'green']
]);
```

### Removing Items from the Cart

Remove items individually or clear the entire cart:

```php
// Remove a specific item
Cart::removeItem('item_id');

// Clear all items from the cart
Cart::clear();

// clears all items and conditions from cart
Cart::reset();

```

### Getting Cart Content and Calculations

Access cart items and perform calculations:

```php
// Get all items
$items = Cart::items();

// Get a specific item
$item = Cart::item('item_id');

// Get cart counts
$totalItems = Cart::count(); // Total quantity of all items
$uniqueItems = Cart::uniqueCount(); // Number of unique items

// Check if cart is empty
$isEmpty = Cart::isEmpty();

// Get cart totals
$subtotal = Cart::subtotal(); // Subtotal before conditions
$total = Cart::total(); // Final total after all conditions
$taxableSubtotal = Cart::getTaxableSubtotal(); // Subtotal of taxable items only
```

### Understanding Conditions

Conditions are adjustments that can be applied to cart items or the entire cart. There are several types:

- **Percentage Conditions**: Apply percentage-based adjustments (e.g., 10% discount)
- **Fixed Conditions**: Apply fixed-amount adjustments (e.g., $5 off)
- **Tax Conditions**: Special conditions for tax calculations

Conditions can target:
- **Individual Items**: Applied to specific cart items
- **Cart Subtotal**: Applied to the entire cart subtotal
- **Taxable Items**: Applied only to items marked as taxable

### Adding Conditions

Add conditions to the cart or specific items:

```php
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;

// Add a 10% discount to the cart using condition class
$discount = new PercentageCondition(
    name: '10% Off Sale',
    value: -10, // Negative for discount
    target: ConditionTarget::SUBTOTAL
);
Cart::addCondition($discount);

// Add a $5 shipping fee using condition class
$shipping = new FixedCondition(
    name: 'Shipping Fee',
    value: 5.00,
    target: ConditionTarget::SUBTOTAL
);
Cart::addCondition($shipping);

// Add condition using array format with specific condition class
$memberDiscount = PercentageCondition::make([
    'name' => 'Member Discount',
    'value' => -15,
    'target' => ConditionTarget::SUBTOTAL
]);
Cart::addCondition($memberDiscount);

// Add condition to a specific item
$itemDiscount = new PercentageCondition(
    name: 'Item Discount',
    value: -20,
    target: ConditionTarget::ITEM
);
Cart::addItemCondition('item_id', $itemDiscount);

// Add multiple conditions at once
Cart::addConditions([
    new FixedCondition('Processing Fee', 2.50, ConditionTarget::SUBTOTAL),
    new PercentageCondition('Bulk Discount', -5, ConditionTarget::SUBTOTAL)
]);
```

### Removing Conditions

Remove conditions from the cart or items:

```php
// Remove a specific condition from the cart
Cart::removeCondition('10% Off Sale');

// Remove a condition from a specific item
Cart::removeItemCondition('item_id', 'Item Discount');

// Clear all cart conditions
Cart::clearConditions();
```

### Marking Items as Non-Taxable

By default, all items are taxable. You can mark specific items as non-taxable:

```php
// Add non-taxable item
Cart::addItem([
    'id' => 4,
    'name' => 'Non-taxable Service',
    'price' => 100.00,
    'quantity' => 1,
    'taxable' => false
]);

// Update existing item to be non-taxable
Cart::updateItem('item_id', ['taxable' => false]);
```

## Configuration Options

### Storage Configuration

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

### Session Key Configuration

Customize the session key when using session storage:

```php
'session_key' => 'my_custom_cart_key',
```

### Currency and Locale Configuration

Set the default currency and locale for price formatting:

```php
'currency' => 'USD', // ISO currency code
'locale' => 'en_US', // Locale for formatting
```

### Custom Models Configuration

When using database storage, you can specify custom models:

```php
'cart_model' => App\Models\CustomCart::class,
'cart_item_model' => App\Models\CustomCartItem::class,
```

### Compound Discounts

Control how multiple discounts are calculated:

```php
// Sequential calculation (each discount applies to the result of previous discounts)
'compound_discounts' => true,

// Parallel calculation (all discounts apply to the original price)
'compound_discounts' => false,
```

### Cart Cleanup

Configure automatic cleanup of old carts when using database storage:

```php
'cleanup' => [
    'enabled' => true,
    'lifetime' => 60 * 24 * 7, // 1 week in minutes
],
```

## Working with Prices

FlexiCart uses the Brick/Money library for precise price calculations.

### Creating Price Objects

```php
use Daikazu\Flexicart\Price;

// Create from numeric value
$price = Price::from(29.99);

// Create with specific currency
$price = Price::from(29.99, 'EUR');

// Create from Money object
$money = \Brick\Money\Money::of(29.99, 'USD');
$price = new Price($money);
```

### Price Operations

Price operations return the modified Price object

```php
$price1 = Price::from(10.00);
$price2 = Price::from(5.00);

// Addition
$total = $price1->plus($price2); // $15.00

// Subtraction
$difference = $price1->subtract($price2); // $5.00

// Multiplication
$doubled = $price1->multiplyBy(2); // $20.00

// Division
$half = $price1->divideBy(2); // $5.00
```

### Formatting and Conversion

```php
$price = Price::from(1234.56);

// Get formatted string
$formatted = $price->formatted(); // "$1,234.56"

// Get raw numeric value
$amount = $price->toFloat(); // 1234.56

// Get string representation
$string = (string) $price; // "$1,234.56"

// Get minor value (cents)
$cents = $price->getMinorValue(); // 123456
```

## Displaying the Cart in Blade

Here's an example of how to display cart contents in a Blade template:

```blade
{{-- resources/views/cart.blade.php --}}
@if(Cart::isEmpty())
    <p>Your cart is empty.</p>
@else
    <div class="cart">
        <h2>Shopping Cart</h2>

        <div class="cart-items">
            @foreach(Cart::items() as $item)
                <div class="cart-item">
                    <h4>{{ $item->name }}</h4>
                    <p>Price: {{ $item->unitPrice()->formatted() }}</p>
                    <p>Quantity: {{ $item->quantity }}</p>
                    <p>Subtotal: {{ $item->subtotal()->formatted() }}</p>

                    @if($item->attributes)
                        <div class="attributes">
                            @foreach($item->attributes as $key => $value)
                                <span class="attribute">{{ $key }}: {{ $value }}</span>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('cart.remove') }}" method="POST">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <button type="submit">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="cart-totals">
            <p>Subtotal: {{ Cart::subtotal()->formatted() }}</p>
            <p><strong>Total: {{ Cart::total()->formatted() }}</strong></p>
        </div>

        <div class="cart-actions">
            <a href="{{ route('cart.clear') }}" class="btn btn-secondary">Clear Cart</a>
            <a href="{{ route('checkout') }}" class="btn btn-primary">Checkout</a>
        </div>
    </div>
@endif
```

## Database Storage Notes

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

## Extending the Package

### Custom Condition Class

Create custom condition types by extending the base Condition class:

```php
<?php

namespace App\Cart\Conditions;

use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Price;

class BuyOneGetOneCondition extends Condition
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    public function calculate(?Price $price = null): Price
    {
        // Custom calculation logic
        // For example, buy one get one 50% off
        if ($this->attributes->quantity >= 2) {
            $discount = $price->multipliedBy(0.5);
            return $discount->multipliedBy(-1); // Negative for discount
        }

        return Price::from(0);
    }

    public function formattedValue(): string
    {
        return 'Buy One Get One 50% Off';
    }
}
```

### Custom Storage Method

Implement custom storage by creating a class that implements `StorageInterface`:

```php
<?php

namespace App\Cart\Storage;

use Daikazu\Flexicart\Contracts\StorageInterface;

class RedisCartStorage implements StorageInterface
{
    public function get(string $key): array
    {
        // Implement Redis get logic
    }

    public function put(string $key, array $data): void
    {
        // Implement Redis put logic
    }

    public function forget(string $key): void
    {
        // Implement Redis forget logic
    }

    public function flush(): void
    {
        // Implement Redis flush logic
    }
}
```

Then configure it in your `flexicart.php` config file:

```php
'storage_class' => App\Cart\Storage\RedisCartStorage::class,
```

## Testing

The package comes with comprehensive tests. To run them:

```bash
composer test
```

## Troubleshooting

### Common Issues

**Cart data not persisting between requests**
- Ensure sessions are properly configured in your Laravel application
- If using database storage, verify migrations have been run
- Check that the `CART_STORAGE` environment variable is set correctly

**Price calculation errors**
- Verify that all price values are numeric
- Ensure currency codes are valid ISO codes

**Condition not applying correctly**
- Verify condition targets are set appropriately
- Check condition order values if multiple conditions exist
- Ensure condition values are properly signed (negative for discounts)

**Memory issues with large carts**
- Consider implementing cart item limits
- Don't use session a storage as cookies due to limits to cookie size
- Use database storage for better memory management
- Implement cart cleanup for old/abandoned carts

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🔮 Future Roadmap
- Event system for cart actions
- Built-in coupon code support
- Advanced reporting and analytics
- REST API endpoints


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mike Wall](https://github.com/daikazu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
