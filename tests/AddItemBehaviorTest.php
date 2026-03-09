<?php

declare(strict_types=1);

use Daikazu\Flexicart\Enums\AddItemBehavior;

test('AddItemBehavior enum has expected cases', function (): void {
    expect(AddItemBehavior::cases())->toHaveCount(2)
        ->and(AddItemBehavior::Update->value)->toBe('update')
        ->and(AddItemBehavior::New->value)->toBe('new');
});

test('AddItemBehavior can be created from string', function (): void {
    expect(AddItemBehavior::from('update'))->toBe(AddItemBehavior::Update)
        ->and(AddItemBehavior::from('new'))->toBe(AddItemBehavior::New);
});
