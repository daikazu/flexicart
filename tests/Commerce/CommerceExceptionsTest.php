<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;

describe('Commerce Exceptions', function (): void {
    test('CommerceConnectionException is throwable with message', function (): void {
        $e = new CommerceConnectionException('Connection refused');
        expect($e)->toBeInstanceOf(\Exception::class)
            ->and($e->getMessage())->toBe('Connection refused');
    });

    test('CommerceAuthenticationException is throwable with message', function (): void {
        $e = new CommerceAuthenticationException('Invalid token');
        expect($e)->toBeInstanceOf(\Exception::class)
            ->and($e->getMessage())->toBe('Invalid token');
    });
});
