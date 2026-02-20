<?php

declare(strict_types=1);

describe('Commerce Config', function (): void {
    test('commerce config has required keys', function (): void {
        $config = config('flexicart.commerce');

        expect($config)->toBeArray()
            ->and($config)->toHaveKeys([
                'enabled',
                'driver',
                'base_url',
                'token',
                'timeout',
                'cache',
            ])
            ->and($config['cache'])->toHaveKeys(['enabled', 'ttl']);
    });

    test('commerce is disabled by default', function (): void {
        expect(config('flexicart.commerce.enabled'))->toBeFalse();
    });

    test('commerce driver defaults to auto', function (): void {
        expect(config('flexicart.commerce.driver'))->toBe('auto');
    });
});
