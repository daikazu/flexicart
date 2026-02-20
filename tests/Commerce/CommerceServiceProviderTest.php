<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;

describe('Commerce Service Provider', function (): void {

    test('CommerceClient is not bound when commerce is disabled', function (): void {
        config()->set('flexicart.commerce.enabled', false);

        expect(app()->bound(CommerceClient::class))->toBeFalse();
    });

    test('CommerceClient is bound as singleton when commerce is enabled', function (): void {
        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
        config()->set('flexicart.commerce.token', 'test-token');

        // Re-register to pick up config change
        $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
        $provider->packageRegistered();

        expect(app()->bound(CommerceClient::class))->toBeTrue();

        $client1 = app(CommerceClient::class);
        $client2 = app(CommerceClient::class);

        expect($client1)->toBeInstanceOf(CommerceClient::class)
            ->and($client1)->toBe($client2);
    });
});
