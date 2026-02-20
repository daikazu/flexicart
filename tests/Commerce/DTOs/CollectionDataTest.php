<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\CollectionData;

describe('CollectionData', function (): void {
    test('creates from API array', function (): void {
        $data = CollectionData::fromArray([
            'slug'        => 'patches',
            'name'        => 'Patches',
            'type'        => 'category',
            'description' => 'All patches',
            'is_active'   => true,
            'parent_id'   => null,
            'children'    => [],
            'products'    => [['slug' => 'p1']],
        ]);

        expect($data->slug)->toBe('patches')
            ->and($data->name)->toBe('Patches')
            ->and($data->type)->toBe('category')
            ->and($data->isActive)->toBeTrue()
            ->and($data->products)->toHaveCount(1);
    });
});
