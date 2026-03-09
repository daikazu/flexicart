<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Daikazu\Flexicart\Commerce\LocalCommerceDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class)) {
        $this->markTestSkipped('flexi-commerce package is not installed.');
    }
});

describe('LocalCommerceDriver Store Scoping', function (): void {

    test('products() returns only store-scoped products when storeId is set', function (): void {
        $store = \Daikazu\FlexiCommerce\Models\Store::create([
            'store_id'  => 'test-store',
            'name'      => 'Test Store',
            'is_active' => true,
        ]);

        $allowed = \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Allowed', 'slug' => 'allowed', 'status' => 'active', 'type' => 'simple',
        ]);
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Denied', 'slug' => 'denied', 'status' => 'active', 'type' => 'simple',
        ]);

        $store->products()->attach($allowed->id);

        $driver = new LocalCommerceDriver(storeId: 'test-store');
        $result = $driver->products();

        expect($result)->toHaveCount(1)
            ->and($result->items()[0])->toBeInstanceOf(ProductData::class)
            ->and($result->items()[0]->slug)->toBe('allowed');
    });

    test('products() returns all products when storeId is null', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'A', 'slug' => 'a', 'status' => 'active', 'type' => 'simple',
        ]);
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'B', 'slug' => 'b', 'status' => 'active', 'type' => 'simple',
        ]);

        $driver = new LocalCommerceDriver;
        $result = $driver->products();

        expect($result)->toHaveCount(2);
    });

    test('product() throws when product not in store', function (): void {
        \Daikazu\FlexiCommerce\Models\Store::create([
            'store_id' => 'restricted', 'name' => 'Restricted', 'is_active' => true,
        ]);

        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Outside', 'slug' => 'outside', 'status' => 'active', 'type' => 'simple',
        ]);

        $driver = new LocalCommerceDriver(storeId: 'restricted');
        $driver->product('outside');
    })->throws(CommerceConnectionException::class);

    test('product() returns product when it is in the store', function (): void {
        $store = \Daikazu\FlexiCommerce\Models\Store::create([
            'store_id' => 'has-product', 'name' => 'Has Product', 'is_active' => true,
        ]);

        $product = \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Inside', 'slug' => 'inside', 'status' => 'active', 'type' => 'simple',
        ]);
        $store->products()->attach($product->id);

        $driver = new LocalCommerceDriver(storeId: 'has-product');
        $result = $driver->product('inside');

        expect($result->slug)->toBe('inside');
    });

    test('resolvePrice() throws for product not in store', function (): void {
        \Daikazu\FlexiCommerce\Models\Store::create([
            'store_id' => 'price-check', 'name' => 'Price Check', 'is_active' => true,
        ]);

        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Blocked', 'slug' => 'blocked', 'status' => 'active', 'type' => 'simple',
        ]);

        $driver = new LocalCommerceDriver(storeId: 'price-check');
        $driver->resolvePrice('blocked', ['quantity' => 1, 'currency' => 'USD']);
    })->throws(CommerceConnectionException::class);

    test('throws when store_id does not exist', function (): void {
        $driver = new LocalCommerceDriver(storeId: 'nonexistent-store');
        $driver->products();
    })->throws(CommerceConnectionException::class);
});
