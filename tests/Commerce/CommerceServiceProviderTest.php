<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Contracts\CommerceClientInterface;

describe('Commerce Service Provider', function (): void {

    test('CommerceClient is not bound when commerce is disabled', function (): void {
        config()->set('flexicart.commerce.enabled', false);

        expect(app()->bound(CommerceClient::class))->toBeFalse()
            ->and(app()->bound(CommerceClientInterface::class))->toBeFalse();
    });

    test('CommerceClient is bound as singleton when commerce is enabled', function (): void {
        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.driver', 'api');
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

    test('CommerceClientInterface resolves to CommerceClient when commerce is enabled', function (): void {
        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.driver', 'api');
        config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
        config()->set('flexicart.commerce.token', 'test-token');

        $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
        $provider->packageRegistered();

        expect(app()->bound(CommerceClientInterface::class))->toBeTrue();
        expect(app(CommerceClientInterface::class))->toBeInstanceOf(CommerceClient::class);
    });

    test('driver=api always binds CommerceClient regardless of local availability', function (): void {
        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.driver', 'api');
        config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
        config()->set('flexicart.commerce.token', 'test-token');

        $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
        $provider->packageRegistered();

        expect(app(CommerceClientInterface::class))->toBeInstanceOf(CommerceClient::class);
    });

    test('driver=local throws when flexi-commerce is not installed', function (): void {
        if (class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class)) {
            $this->markTestSkipped('flexi-commerce is installed — cannot test missing-package error.');
        }

        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.driver', 'local');

        $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
        $provider->packageRegistered();

        app(CommerceClientInterface::class);
    })->throws(\RuntimeException::class, 'flexi-commerce');

    test('interface not bound when commerce is disabled', function (): void {
        config()->set('flexicart.commerce.enabled', false);

        expect(app()->bound(CommerceClientInterface::class))->toBeFalse();
    });
});
