<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Illuminate\Support\Facades\Http;

describe('CommerceClient Store Header', function (): void {

    test('sends X-Store-Id header when storeId is configured', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => 15, 'current_page' => 1],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://central.test/api/flexi-commerce/v1',
            token: 'test-token',
            storeId: 'my-store',
            cacheEnabled: false,
        );

        $client->products();

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('X-Store-Id', 'my-store');
        });
    });

    test('does not send X-Store-Id header when storeId is null', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => 15, 'current_page' => 1],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://central.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: false,
        );

        $client->products();

        Http::assertSent(function ($request): bool {
            return ! $request->hasHeader('X-Store-Id');
        });
    });

    test('different storeIds produce different cache keys', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => 15, 'current_page' => 1],
            ]),
        ]);

        $clientA = new CommerceClient(
            baseUrl: 'https://central.test/api/flexi-commerce/v1',
            token: 'test-token',
            storeId: 'store-x',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $clientB = new CommerceClient(
            baseUrl: 'https://central.test/api/flexi-commerce/v1',
            token: 'test-token',
            storeId: 'store-y',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $clientA->products();
        $clientB->products();

        // Both should have made HTTP requests (not shared cache)
        Http::assertSentCount(2);
    });
});
