<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class ProductData
{
    /**
     * @param  array<int, array<string, mixed>>  $prices
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, array<string, mixed>>  $addonGroups
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $type,
        public ?string $description,
        public array $prices,
        public array $options,
        public array $variants,
        public array $addonGroups,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'],
            description: $data['description'] ?? null,
            prices: $data['prices'] ?? [],
            options: $data['options'] ?? [],
            variants: $data['variants'] ?? [],
            addonGroups: $data['addon_groups'] ?? [],
            raw: $data,
        );
    }
}
