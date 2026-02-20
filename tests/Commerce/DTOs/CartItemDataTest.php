<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;

describe('CartItemData', function (): void {
    test('creates from API array', function (): void {
        $data = CartItemData::fromArray([
            'id'         => 'P-2-00:backing=iron-on',
            'name'       => 'Custom Patches - 2 Inch',
            'price'      => 6.02,
            'quantity'   => 25,
            'attributes' => [
                'product_slug'  => 'custom-patches',
                'variant_id'    => 42,
                'sku'           => 'P-2-00',
                'option_values' => ['size' => '2 Inch'],
                'source'        => 'flexi-commerce',
            ],
            'conditions' => [
                [
                    'name'       => 'Addon: Iron-On Backing',
                    'value'      => 0.12,
                    'type'       => 'fixed',
                    'target'     => 'item',
                    'attributes' => ['addon_code' => 'iron-on', 'group_code' => 'backing', 'modifier_id' => 7],
                    'order'      => 0,
                    'taxable'    => true,
                ],
            ],
        ]);

        expect($data->id)->toBe('P-2-00:backing=iron-on')
            ->and($data->name)->toBe('Custom Patches - 2 Inch')
            ->and($data->price)->toBe(6.02)
            ->and($data->quantity)->toBe(25)
            ->and($data->attributes)->toBeArray()
            ->and($data->conditions)->toHaveCount(1);
    });

    test('toCartArray converts conditions to ConditionInterface instances', function (): void {
        $data = CartItemData::fromArray([
            'id'         => 'P-001',
            'name'       => 'Product',
            'price'      => 10.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => [
                [
                    'name'       => 'Addon: Test',
                    'value'      => 1.50,
                    'type'       => 'fixed',
                    'target'     => 'item',
                    'attributes' => [],
                    'order'      => 0,
                    'taxable'    => true,
                ],
            ],
        ]);

        $arr = $data->toCartArray();

        expect($arr)->toHaveKeys(['id', 'name', 'price', 'quantity', 'attributes', 'conditions'])
            ->and($arr['id'])->toBe('P-001')
            ->and($arr['price'])->toBe(10.00)
            ->and($arr['conditions'])->toHaveCount(1)
            ->and($arr['conditions'][0])->toBeInstanceOf(ConditionInterface::class);
    });

    test('toCartArray with empty conditions returns empty array', function (): void {
        $data = CartItemData::fromArray([
            'id'         => 'P-001',
            'name'       => 'Product',
            'price'      => 10.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => [],
        ]);

        expect($data->toCartArray()['conditions'])->toBe([]);
    });
});
