<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Daikazu\Flexicart\Commerce\LocalCommerceDriver;

beforeEach(function (): void {
    if (! class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class)) {
        $this->markTestSkipped('flexi-commerce package is not installed.');
    }

    $this->driver = new LocalCommerceDriver;
});

describe('LocalCommerceDriver Products', function (): void {

    test('products() returns paginated ProductData', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name'   => 'Test Product',
            'slug'   => 'test-product',
            'status' => 'active',
            'type'   => 'simple',
        ]);

        $result = $this->driver->products();

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result)->toHaveCount(1)
            ->and($result->items()[0])->toBeInstanceOf(ProductData::class)
            ->and($result->items()[0]->slug)->toBe('test-product');
    });

    test('products() excludes inactive products', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name'   => 'Active',
            'slug'   => 'active',
            'status' => 'active',
            'type'   => 'simple',
        ]);
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name'   => 'Draft',
            'slug'   => 'draft',
            'status' => 'draft',
            'type'   => 'simple',
        ]);

        $result = $this->driver->products();

        expect($result)->toHaveCount(1)
            ->and($result->items()[0]->slug)->toBe('active');
    });

    test('products() respects per_page and page filters', function (): void {
        foreach (range(1, 5) as $i) {
            \Daikazu\FlexiCommerce\Models\Product::create([
                'name'   => "Product {$i}",
                'slug'   => "product-{$i}",
                'status' => 'active',
                'type'   => 'simple',
            ]);
        }

        $result = $this->driver->products(['per_page' => 2, 'page' => 2]);

        expect($result->perPage())->toBe(2)
            ->and($result->currentPage())->toBe(2)
            ->and($result->total())->toBe(5);
    });

    test('product() returns ProductData for active product', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name'   => 'Patches',
            'slug'   => 'patches',
            'status' => 'active',
            'type'   => 'configurable',
        ]);

        $result = $this->driver->product('patches');

        expect($result)->toBeInstanceOf(ProductData::class)
            ->and($result->slug)->toBe('patches')
            ->and($result->name)->toBe('Patches')
            ->and($result->type)->toBe('configurable');
    });

    test('product() throws CommerceConnectionException for non-existent slug', function (): void {
        $this->driver->product('nonexistent');
    })->throws(CommerceConnectionException::class, 'nonexistent');

    test('product() throws CommerceConnectionException for inactive product', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name'   => 'Draft Product',
            'slug'   => 'draft-product',
            'status' => 'draft',
            'type'   => 'simple',
        ]);

        $this->driver->product('draft-product');
    })->throws(CommerceConnectionException::class, 'draft-product');
});

describe('LocalCommerceDriver Collections', function (): void {

    test('collections() returns paginated CollectionData', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name'      => 'Summer',
            'slug'      => 'summer',
            'is_active' => true,
            'type'      => 'category',
        ]);

        $result = $this->driver->collections();

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result)->toHaveCount(1)
            ->and($result->items()[0])->toBeInstanceOf(CollectionData::class)
            ->and($result->items()[0]->slug)->toBe('summer');
    });

    test('collections() excludes inactive collections', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name'      => 'Active',
            'slug'      => 'active-col',
            'is_active' => true,
            'type'      => 'category',
        ]);
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name'      => 'Inactive',
            'slug'      => 'inactive-col',
            'is_active' => false,
            'type'      => 'category',
        ]);

        $result = $this->driver->collections();

        expect($result)->toHaveCount(1)
            ->and($result->items()[0]->slug)->toBe('active-col');
    });

    test('collections() respects per_page and page filters', function (): void {
        foreach (range(1, 5) as $i) {
            \Daikazu\FlexiCommerce\Models\ProductCollection::create([
                'name'      => "Collection {$i}",
                'slug'      => "collection-{$i}",
                'is_active' => true,
                'type'      => 'category',
            ]);
        }

        $result = $this->driver->collections(['per_page' => 2, 'page' => 2]);

        expect($result->perPage())->toBe(2)
            ->and($result->currentPage())->toBe(2)
            ->and($result->total())->toBe(5);
    });

    test('collection() returns CollectionData for active collection', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name'      => 'Summer',
            'slug'      => 'summer',
            'is_active' => true,
            'type'      => 'merchandising',
        ]);

        $result = $this->driver->collection('summer');

        expect($result)->toBeInstanceOf(CollectionData::class)
            ->and($result->slug)->toBe('summer')
            ->and($result->name)->toBe('Summer');
    });

    test('collection() throws CommerceConnectionException for non-existent slug', function (): void {
        $this->driver->collection('nonexistent');
    })->throws(CommerceConnectionException::class, 'nonexistent');

    test('collection() throws CommerceConnectionException for inactive collection', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name'      => 'Inactive Collection',
            'slug'      => 'inactive-col',
            'is_active' => false,
            'type'      => 'category',
        ]);

        $this->driver->collection('inactive-col');
    })->throws(CommerceConnectionException::class, 'inactive-col');
});
