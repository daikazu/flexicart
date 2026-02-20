<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Illuminate\Support\Facades\Http;

function makeErrorClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Error Handling', function (): void {

    test('throws CommerceAuthenticationException on 401', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Invalid API token.'],
            ], 401),
        ]);

        makeErrorClient()->products();
    })->throws(CommerceAuthenticationException::class, 'Invalid API token.');

    test('throws CommerceConnectionException on 404', function (): void {
        Http::fake([
            '*/products/nonexistent' => Http::response([
                'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => "No active product found with slug 'nonexistent'."],
            ], 404),
        ]);

        makeErrorClient()->product('nonexistent');
    })->throws(CommerceConnectionException::class, "No active product found with slug 'nonexistent'.");

    test('throws CommerceConnectionException on 422', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'error' => ['code' => 'VARIANT_NOT_FOUND', 'message' => 'Variant 9999 not found.'],
            ], 422),
        ]);

        makeErrorClient()->resolvePrice('patches', [
            'variant_id' => 9999,
            'quantity'   => 1,
            'currency'   => 'USD',
        ]);
    })->throws(CommerceConnectionException::class, 'Variant 9999 not found.');

    test('throws CommerceConnectionException on 503', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'error' => ['code' => 'API_DISABLED', 'message' => 'API is currently disabled.'],
            ], 503),
        ]);

        makeErrorClient()->products();
    })->throws(CommerceConnectionException::class, 'API is currently disabled.');

    test('throws CommerceConnectionException on connection failure', function (): void {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        makeErrorClient()->products();
    })->throws(CommerceConnectionException::class, 'Could not connect to commerce API');

    test('CommerceConnectionException includes HTTP status code', function (): void {
        Http::fake([
            '*/products/nonexistent' => Http::response([
                'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Not found.'],
            ], 404),
        ]);

        try {
            makeErrorClient()->product('nonexistent');
        } catch (CommerceConnectionException $e) {
            expect($e->getCode())->toBe(404);
        }
    });
});
