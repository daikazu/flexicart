<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Illuminate\Support\Facades\Http;

describe('CommerceClient Caching', function (): void {

    test('GET requests are cached when caching is enabled', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data'  => [['slug' => 'p1', 'name' => 'Product 1', 'type' => 'simple']],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $client->products();
        $client->products();

        Http::assertSentCount(1);
    });

    test('GET requests bypass cache when caching is disabled', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data'  => [],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: false,
            cacheTtl: 300,
        );

        $client->products();
        $client->products();

        Http::assertSentCount(2);
    });

    test('POST requests are never cached', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'data' => [
                    'product_slug' => 'patches',
                    'variant'      => null,
                    'quantity'     => 1,
                    'currency'     => 'USD',
                    'unit_price'   => '5.00',
                    'tier_applied' => null,
                    'addons'       => [],
                    'line_total'   => '5.00',
                ],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $client->resolvePrice('patches', ['quantity' => 1, 'currency' => 'USD']);
        $client->resolvePrice('patches', ['quantity' => 1, 'currency' => 'USD']);

        Http::assertSentCount(2);
    });

    test('different query params produce different cache keys', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data'  => [],
                'links' => [],
                'meta'  => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $client->products(['page' => 1]);
        $client->products(['page' => 2]);

        Http::assertSentCount(2);
    });
});
