<?php

declare(strict_types=1);

describe('Commerce Config', function (): void {
    test('commerce config has required keys', function (): void {
        $config = config('flexicart.commerce');

        expect($config)->toBeArray()
            ->and($config)->toHaveKeys([
                'enabled',
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
});
