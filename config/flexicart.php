<?php

declare(strict_types=1);

// config for Daikazu/Cart

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    |
    | This value determines which storage driver the cart will use.
    | Options: 'session', 'database'
    |
    */
    'storage' => env('CART_STORAGE', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Custom Storage Class
    |--------------------------------------------------------------------------
    |
    | If you want to use a custom storage implementation, you can specify
    | the fully qualified class name here. The class must implement
    | the StorageInterface.
    |
    */
    'storage_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | This value determines the session key to use for the cart when
    | using the session storage driver.
    |
    */
    'session_key' => 'flexible_cart',

    /*
    |--------------------------------------------------------------------------
    | Cart Model
    |--------------------------------------------------------------------------
    |
    | You can customize the model class used for the cart when using the
    | database storage driver. The model must implement the required interface.
    |
    */
    'cart_model' => Daikazu\Flexicart\Models\CartModel::class,

    /*
    |--------------------------------------------------------------------------
    | Cart Item Model
    |--------------------------------------------------------------------------
    |
    | You can customize the model class used for the cart items when using the
    | database storage driver. The model must implement the required interface.
    |
    */
    'cart_item_model' => Daikazu\Flexicart\Models\CartItemModel::class,

    /*
     * Flexicart uses https://github.com/brick/money under the hood for handling currency.
     */
    'currency' => 'USD',
    'locale'   => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Compound Discounts
    |--------------------------------------------------------------------------
    |
    | When enabled, discounts will be applied sequentially, where each discount
    | is calculated based on the result of previous discounts. When disabled,
    | all discounts are calculated based on the original price.
    |
    */
    'compound_discounts' => env('CART_COMPOUND_DISCOUNTS', false),

    /*
    |--------------------------------------------------------------------------
    | Cart Cleanup
    |--------------------------------------------------------------------------
    |
    | These settings control how old carts are cleaned up from the database.
    | The lifetime is specified in minutes.
    |
    */
    'cleanup' => [
        'enabled'  => true,
        'lifetime' => 60 * 24 * 7, // 1 week by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Events
    |--------------------------------------------------------------------------
    |
    | When enabled, the cart will dispatch Laravel events for various actions
    | such as adding items, removing items, clearing the cart, etc. This allows
    | you to hook into cart actions for analytics, inventory management, etc.
    |
    */
    'events' => [
        'enabled' => env('CART_EVENTS_ENABLED', true),
    ],

];
