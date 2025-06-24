<?php

declare(strict_types=1);

use Daikazu\Flexicart\Storage\SessionStorage;
use Illuminate\Session\SessionManager;

describe('SessionStorage', function (): void {
    describe('Cart ID Methods', function (): void {

        test('getCartId returns the session key', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);

            // Create the session storage with a custom key
            $customKey = 'custom_cart_key';
            $sessionStorage = new SessionStorage($sessionManager, $customKey);

            // Get the cart ID
            $cartId = $sessionStorage->getCartId();

            // Verify it's the same as the session key
            expect($cartId)->toBe($customKey);
        });

        test('getCartById returns cart data for matching ID', function (): void {
            // Prepare test data
            $testData = [
                'items'      => ['item1', 'item2'],
                'conditions' => ['condition1'],
            ];

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with('custom_cart_key', [])
                ->andReturn($testData);

            // Create the session storage with a custom key
            $customKey = 'custom_cart_key';
            $sessionStorage = new SessionStorage($sessionManager, $customKey);

            // Get the cart by ID (matching the session key)
            $retrievedCart = $sessionStorage->getCartById($customKey);

            // Verify the cart data
            expect($retrievedCart)->toBe($testData);
        });

        test('getCartById returns null for non-matching ID', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);

            // Create the session storage with a custom key
            $customKey = 'custom_cart_key';
            $sessionStorage = new SessionStorage($sessionManager, $customKey);

            // Try to get a cart with a different ID
            $retrievedCart = $sessionStorage->getCartById('different_key');

            // Verify it returns null
            expect($retrievedCart)->toBeNull();
        });
    });

    describe('Constructor and Initialization', function (): void {
        test('can be instantiated with default session key', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Verify the instance was created
            expect($sessionStorage)->toBeInstanceOf(SessionStorage::class);
        });

        test('can be instantiated with custom session key', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);

            // Create the session storage with custom key
            $sessionStorage = new SessionStorage($sessionManager, 'custom_key');

            // Verify the instance was created
            expect($sessionStorage)->toBeInstanceOf(SessionStorage::class);
        });

        test('uses default key when provided key is null', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn([]);

            // Create the session storage with null key
            $sessionStorage = new SessionStorage($sessionManager, null);

            // Call get to verify the key used
            $sessionStorage->get();
        });

        test('uses default key when provided key is empty string', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn([]);

            // Create the session storage with empty string key
            $sessionStorage = new SessionStorage($sessionManager, '');

            // Call get to verify the key used
            $sessionStorage->get();
        });

        test('uses default key when provided key is "0"', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn([]);

            // Create the session storage with "0" key
            $sessionStorage = new SessionStorage($sessionManager, '0');

            // Call get to verify the key used
            $sessionStorage->get();
        });
    });

    describe('Get Method', function (): void {
        test('returns empty array when session is empty', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn([]);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Get the cart data
            $data = $sessionStorage->get();

            // Verify the data structure
            expect($data)->toBeArray()
                ->and($data)->toHaveKeys(['items', 'conditions'])
                ->and($data['items'])->toBeArray()
                ->and($data['items'])->toBeEmpty()
                ->and($data['conditions'])->toBeArray()
                ->and($data['conditions'])->toBeEmpty();
        });

        test('returns data in new format when session has new format data', function (): void {
            // Prepare test data in new format
            $testData = [
                'items'      => ['item1', 'item2'],
                'conditions' => ['condition1'],
            ];

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn($testData);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Get the cart data
            $data = $sessionStorage->get();

            // Verify the data is returned as-is
            expect($data)->toBe($testData);
        });

        test('converts old format data to new format', function (): void {
            // Prepare test data in old format (just items array)
            $oldFormatData = ['item1', 'item2'];

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('get')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), [])
                ->andReturn($oldFormatData);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Get the cart data
            $data = $sessionStorage->get();

            // Verify the data is converted to new format
            expect($data)->toBeArray()
                ->and($data)->toHaveKeys(['items', 'conditions'])
                ->and($data['items'])->toBe($oldFormatData)
                ->and($data['conditions'])->toBeArray()
                ->and($data['conditions'])->toBeEmpty();
        });
    });

    describe('Put Method', function (): void {
        test('stores data in session and returns it', function (): void {
            // Prepare test data
            $testData = [
                'items'      => ['item1', 'item2'],
                'conditions' => ['condition1'],
            ];

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('put')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), $testData)
                ->andReturn(null);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Put the cart data
            $result = $sessionStorage->put($testData);

            // Verify the result
            expect($result)->toBe($testData);
        });

        test('converts old format data to new format before storing', function (): void {
            // Prepare test data in old format (just items array)
            $oldFormatData = ['item1', 'item2'];

            // Expected data in new format
            $newFormatData = [
                'items'      => $oldFormatData,
                'conditions' => [],
            ];

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('put')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'), $newFormatData)
                ->andReturn(null);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Put the cart data in old format
            $result = $sessionStorage->put($oldFormatData);

            // Verify the result is in new format
            expect($result)->toBe($newFormatData);
        });

        test('uses custom session key when provided', function (): void {
            // Prepare test data
            $testData = [
                'items'      => ['item1', 'item2'],
                'conditions' => ['condition1'],
            ];

            // Custom session key
            $customKey = 'custom_cart_key';

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('put')
                ->once()
                ->with($customKey, $testData)
                ->andReturn(null);

            // Create the session storage with custom key
            $sessionStorage = new SessionStorage($sessionManager, $customKey);

            // Put the cart data
            $sessionStorage->put($testData);
        });
    });

    describe('Flush Method', function (): void {
        test('removes data from session', function (): void {
            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('forget')
                ->once()
                ->with(config('flexicart.session_key', 'flexible_cart'))
                ->andReturn(null);

            // Create the session storage
            $sessionStorage = new SessionStorage($sessionManager);

            // Flush the cart data
            $sessionStorage->flush();
        });

        test('uses custom session key when provided', function (): void {
            // Custom session key
            $customKey = 'custom_cart_key';

            // Mock the session manager
            $sessionManager = mock(SessionManager::class);
            $sessionManager->shouldReceive('forget')
                ->once()
                ->with($customKey)
                ->andReturn(null);

            // Create the session storage with custom key
            $sessionStorage = new SessionStorage($sessionManager, $customKey);

            // Flush the cart data
            $sessionStorage->flush();
        });
    });
});
