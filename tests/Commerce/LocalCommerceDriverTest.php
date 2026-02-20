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

function createConfiguredProduct(): array
{
    $product = \Daikazu\FlexiCommerce\Models\Product::create([
        'name'   => 'Patches',
        'slug'   => 'patches',
        'status' => 'active',
        'type'   => 'configurable',
    ]);

    $option = \Daikazu\FlexiCommerce\Models\ProductOption::create([
        'product_id'  => $product->id,
        'name'        => 'Size',
        'code'        => 'size',
        'is_variant'  => true,
        'is_required' => true,
    ]);

    $value = \Daikazu\FlexiCommerce\Models\ProductOptionValue::create([
        'product_option_id' => $option->id,
        'name'              => '2 Inch',
        'code'              => '2-00',
        'is_active'         => true,
    ]);

    $variant = \Daikazu\FlexiCommerce\Models\ProductVariant::create([
        'product_id' => $product->id,
        'sku'        => 'PATCHES-2-00',
        'name'       => '2 Inch',
        'is_active'  => true,
        'signature'  => 'size:2-00',
    ]);

    $variant->optionValues()->attach($value->id, ['product_option_id' => $option->id]);

    $variant->prices()->create([
        'key'          => \Daikazu\FlexiCommerce\Enums\PriceKey::Retail->value,
        'currency'     => 'USD',
        'amount_minor' => 602,
    ]);

    $group = \Daikazu\FlexiCommerce\Models\AddonGroup::create([
        'code'                 => 'backing',
        'name'                 => 'Backing',
        'selection_type'       => 'single',
        'min_selected'         => 0,
        'max_selected'         => 1,
        'free_selection_limit' => 0,
    ]);

    $addon = \Daikazu\FlexiCommerce\Models\Addon::create([
        'code'      => 'iron-on',
        'name'      => 'Iron-On',
        'is_active' => true,
    ]);

    $item = \Daikazu\FlexiCommerce\Models\AddonGroupItem::create([
        'addon_group_id'   => $group->id,
        'addon_id'         => $addon->id,
        'default_selected' => false,
        'is_active'        => true,
        'is_free_eligible' => false,
    ]);

    $modifier = \Daikazu\FlexiCommerce\Models\AddonModifier::create([
        'addon_group_item_id' => $item->id,
        'modifier_type'       => 'per_qty',
        'applies_to'          => 'unit',
    ]);

    $modifier->prices()->create([
        'key'          => \Daikazu\FlexiCommerce\Enums\PriceKey::ModifierAmount->value,
        'currency'     => 'USD',
        'amount_minor' => 12,
    ]);

    $product->addonGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

    return ['product' => $product, 'variant' => $variant];
}

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

describe('LocalCommerceDriver Price Resolution', function (): void {
    test('resolvePrice() returns PriceBreakdownData', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->resolvePrice('patches', [
            'variant_id' => $data['variant']->id,
            'quantity'   => 10,
            'currency'   => 'USD',
        ]);

        expect($result)->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData::class)
            ->and($result->unitPrice)->toBe('6.02')
            ->and($result->quantity)->toBe(10)
            ->and($result->lineTotal)->toBe('60.20');
    });

    test('resolvePrice() with addon selections', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->resolvePrice('patches', [
            'variant_id'       => $data['variant']->id,
            'quantity'         => 10,
            'currency'         => 'USD',
            'addon_selections' => [
                'backing' => ['iron-on' => 1],
            ],
        ]);

        expect($result->lineTotal)->toBe('61.40')
            ->and($result->addons)->toHaveCount(1)
            ->and($result->addons[0]['addon_code'])->toBe('iron-on');
    });

    test('resolvePrice() throws for invalid variant', function (): void {
        createConfiguredProduct();

        $this->driver->resolvePrice('patches', [
            'variant_id' => 9999,
            'quantity'   => 1,
            'currency'   => 'USD',
        ]);
    })->throws(CommerceConnectionException::class);

    test('resolvePrice() throws for non-existent product', function (): void {
        $this->driver->resolvePrice('nonexistent', [
            'quantity' => 1,
            'currency' => 'USD',
        ]);
    })->throws(CommerceConnectionException::class, 'nonexistent');

    test('cartItem() returns CartItemData', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->cartItem('patches', [
            'variant_id'       => $data['variant']->id,
            'quantity'         => 10,
            'currency'         => 'USD',
            'addon_selections' => [
                'backing' => ['iron-on' => 1],
            ],
        ]);

        expect($result)->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\CartItemData::class)
            ->and($result->price)->toBe(6.02)
            ->and($result->quantity)->toBe(10)
            ->and($result->attributes)->toHaveKeys(['product_slug', 'variant_id', 'sku', 'option_values'])
            ->and($result->conditions)->toHaveCount(1);
    });
});
