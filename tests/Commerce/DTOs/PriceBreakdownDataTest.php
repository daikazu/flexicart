<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;

describe('PriceBreakdownData', function (): void {
    test('creates from API array', function (): void {
        $data = PriceBreakdownData::fromArray([
            'product_slug' => 'custom-patches',
            'variant'      => ['id' => 42, 'sku' => 'P-2-00', 'name' => '2 Inch'],
            'quantity'     => 25,
            'currency'     => 'USD',
            'unit_price'   => '6.02',
            'tier_applied' => ['min_qty' => 20, 'max_qty' => 49],
            'addons'       => [
                ['group_code' => 'backing', 'addon_code' => 'iron-on', 'name' => 'Iron-On', 'unit_amount' => '0.12', 'line_amount' => '3.00', 'is_free' => false],
            ],
            'line_total' => '153.50',
        ]);

        expect($data->productSlug)->toBe('custom-patches')
            ->and($data->quantity)->toBe(25)
            ->and($data->currency)->toBe('USD')
            ->and($data->unitPrice)->toBe('6.02')
            ->and($data->lineTotal)->toBe('153.50')
            ->and($data->variant)->not->toBeNull()
            ->and($data->tierApplied)->not->toBeNull()
            ->and($data->addons)->toHaveCount(1);
    });

    test('handles null variant and tier', function (): void {
        $data = PriceBreakdownData::fromArray([
            'product_slug' => 'simple',
            'variant'      => null,
            'quantity'     => 1,
            'currency'     => 'USD',
            'unit_price'   => '5.00',
            'tier_applied' => null,
            'addons'       => [],
            'line_total'   => '5.00',
        ]);

        expect($data->variant)->toBeNull()
            ->and($data->tierApplied)->toBeNull()
            ->and($data->addons)->toBe([]);
    });
});
