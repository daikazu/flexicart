<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\ProductData;

describe('ProductData', function (): void {
    test('creates from API array with all fields', function (): void {
        $data = ProductData::fromArray([
            'slug'         => 'custom-patches',
            'name'         => 'Custom Patches',
            'type'         => 'configurable',
            'description'  => 'High quality patches',
            'meta'         => ['key' => 'value'],
            'prices'       => [['key' => 'retail', 'currency' => 'USD', 'amount_minor' => 602]],
            'options'      => [['code' => 'size', 'name' => 'Size']],
            'variants'     => [['id' => 1, 'sku' => 'P-001']],
            'addon_groups' => [['code' => 'backing']],
        ]);

        expect($data->slug)->toBe('custom-patches')
            ->and($data->name)->toBe('Custom Patches')
            ->and($data->type)->toBe('configurable')
            ->and($data->description)->toBe('High quality patches')
            ->and($data->prices)->toHaveCount(1)
            ->and($data->options)->toHaveCount(1)
            ->and($data->variants)->toHaveCount(1)
            ->and($data->addonGroups)->toHaveCount(1)
            ->and($data->raw)->toBeArray();
    });

    test('handles missing optional fields gracefully', function (): void {
        $data = ProductData::fromArray([
            'slug' => 'simple',
            'name' => 'Simple Product',
            'type' => 'simple',
        ]);

        expect($data->description)->toBeNull()
            ->and($data->prices)->toBe([])
            ->and($data->options)->toBe([])
            ->and($data->variants)->toBe([])
            ->and($data->addonGroups)->toBe([]);
    });
});
