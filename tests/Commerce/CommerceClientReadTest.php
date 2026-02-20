<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

function makeClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Read Endpoints', function (): void {

    test('products() returns paginated ProductData', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [
                    ['slug' => 'product-a', 'name' => 'Product A', 'type' => 'simple'],
                    ['slug' => 'product-b', 'name' => 'Product B', 'type' => 'configurable'],
                ],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2, 'path' => ''],
            ]),
        ]);

        $result = makeClient()->products();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->total())->toBe(2)
            ->and($result->items()[0])->toBeInstanceOf(ProductData::class)
            ->and($result->items()[0]->slug)->toBe('product-a');
    });

    test('products() passes filter query parameters', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data'  => [],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        makeClient()->products(['page' => 2, 'per_page' => 5]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=2')
            && str_contains($request->url(), 'per_page=5'));
    });

    test('product() returns a single ProductData', function (): void {
        Http::fake([
            '*/products/custom-patches' => Http::response([
                'data' => [
                    'slug'         => 'custom-patches',
                    'name'         => 'Custom Patches',
                    'type'         => 'configurable',
                    'options'      => [['code' => 'size', 'name' => 'Size']],
                    'variants'     => [['id' => 1, 'sku' => 'P-001']],
                    'addon_groups' => [],
                ],
            ]),
        ]);

        $result = makeClient()->product('custom-patches');

        expect($result)->toBeInstanceOf(ProductData::class)
            ->and($result->slug)->toBe('custom-patches')
            ->and($result->options)->toHaveCount(1)
            ->and($result->variants)->toHaveCount(1);
    });

    test('collections() returns paginated CollectionData', function (): void {
        Http::fake([
            '*/collections*' => Http::response([
                'data' => [
                    ['slug' => 'patches', 'name' => 'Patches', 'type' => 'category', 'is_active' => true],
                ],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'path' => ''],
            ]),
        ]);

        $result = makeClient()->collections();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->total())->toBe(1)
            ->and($result->items()[0])->toBeInstanceOf(CollectionData::class)
            ->and($result->items()[0]->slug)->toBe('patches');
    });

    test('collection() returns a single CollectionData', function (): void {
        Http::fake([
            '*/collections/patches' => Http::response([
                'data' => [
                    'slug'      => 'patches',
                    'name'      => 'Patches',
                    'type'      => 'category',
                    'is_active' => true,
                    'products'  => [['slug' => 'custom-patches', 'name' => 'Custom Patches', 'type' => 'configurable']],
                ],
            ]),
        ]);

        $result = makeClient()->collection('patches');

        expect($result)->toBeInstanceOf(CollectionData::class)
            ->and($result->slug)->toBe('patches')
            ->and($result->products)->toHaveCount(1);
    });

    test('sends bearer token in Authorization header', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data'  => [],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        makeClient()->products();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    });
});
