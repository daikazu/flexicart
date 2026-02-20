<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Http;

function makeActionClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Action Endpoints', function (): void {

    test('resolvePrice() returns PriceBreakdownData', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'data' => [
                    'product_slug' => 'patches',
                    'variant'      => ['id' => 42, 'sku' => 'P-2-00', 'name' => '2 Inch'],
                    'quantity'     => 10,
                    'currency'     => 'USD',
                    'unit_price'   => '6.02',
                    'tier_applied' => null,
                    'addons'       => [],
                    'line_total'   => '60.20',
                ],
            ]),
        ]);

        $result = makeActionClient()->resolvePrice('patches', [
            'variant_id' => 42,
            'quantity'   => 10,
            'currency'   => 'USD',
        ]);

        expect($result)->toBeInstanceOf(PriceBreakdownData::class)
            ->and($result->unitPrice)->toBe('6.02')
            ->and($result->lineTotal)->toBe('60.20');
    });

    test('resolvePrice() sends correct POST body', function (): void {
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

        makeActionClient()->resolvePrice('patches', [
            'variant_id'       => 42,
            'quantity'         => 10,
            'currency'         => 'USD',
            'addon_selections' => ['backing' => ['iron-on' => 1]],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $body['variant_id'] === 42
                && $body['quantity'] === 10
                && $body['currency'] === 'USD'
                && $body['addon_selections']['backing']['iron-on'] === 1;
        });
    });

    test('cartItem() returns CartItemData', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id'         => 'P-2-00:backing=iron-on',
                    'name'       => 'Custom Patches - 2 Inch',
                    'price'      => 6.02,
                    'quantity'   => 10,
                    'attributes' => ['product_slug' => 'patches', 'sku' => 'P-2-00'],
                    'conditions' => [
                        [
                            'name'       => 'Addon: Iron-On',
                            'value'      => 0.12,
                            'type'       => 'fixed',
                            'target'     => 'item',
                            'attributes' => ['addon_code' => 'iron-on'],
                            'order'      => 0,
                            'taxable'    => true,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = makeActionClient()->cartItem('patches', [
            'variant_id' => 42,
            'quantity'   => 10,
            'currency'   => 'USD',
        ]);

        expect($result)->toBeInstanceOf(CartItemData::class)
            ->and($result->id)->toBe('P-2-00:backing=iron-on')
            ->and($result->price)->toBe(6.02)
            ->and($result->conditions)->toHaveCount(1);
    });

    test('addToCart() adds item to the cart and returns CartItem', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id'         => 'P-2-00',
                    'name'       => 'Custom Patches - 2 Inch',
                    'price'      => 6.02,
                    'quantity'   => 10,
                    'attributes' => ['product_slug' => 'patches'],
                    'conditions' => [],
                ],
            ]),
        ]);

        $storage = new MockStorage;
        $cart = new Cart($storage);

        $item = makeActionClient()->addToCart('patches', [
            'variant_id' => 42,
            'quantity'   => 10,
            'currency'   => 'USD',
        ], $cart);

        expect($item)->toBeInstanceOf(CartItem::class)
            ->and($item->id)->toBe('P-2-00')
            ->and($item->name)->toBe('Custom Patches - 2 Inch')
            ->and($item->quantity)->toBe(10)
            ->and($cart->count())->toBe(10);
    });

    test('addToCart() maps conditions as ConditionInterface instances on the CartItem', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id'         => 'P-2-00',
                    'name'       => 'Patches',
                    'price'      => 6.02,
                    'quantity'   => 1,
                    'attributes' => [],
                    'conditions' => [
                        [
                            'name'       => 'Addon: Iron-On',
                            'value'      => 0.12,
                            'type'       => 'fixed',
                            'target'     => 'item',
                            'attributes' => [],
                            'order'      => 0,
                            'taxable'    => true,
                        ],
                    ],
                ],
            ]),
        ]);

        $storage = new MockStorage;
        $cart = new Cart($storage);

        $item = makeActionClient()->addToCart('patches', [
            'variant_id' => 42,
            'quantity'   => 1,
            'currency'   => 'USD',
        ], $cart);

        expect($item->conditions)->toHaveCount(1)
            ->and($item->conditions->first()->name)->toBe('Addon: Iron-On');
    });
});
