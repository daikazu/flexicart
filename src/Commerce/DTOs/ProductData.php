<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class ProductData
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $prices
     * @param  array<int, array<string, mixed>>  $priceTiers
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, array<string, mixed>>  $addonGroups
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $type,
        public ?string $status,
        public ?string $description,
        public array $meta,
        public array $prices,
        public array $priceTiers,
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
            status: $data['status'] ?? null,
            description: $data['description'] ?? null,
            meta: $data['meta'] ?? [],
            prices: $data['prices'] ?? [],
            priceTiers: $data['price_tiers'] ?? [],
            options: $data['options'] ?? [],
            variants: $data['variants'] ?? [],
            addonGroups: $data['addon_groups'] ?? [],
            raw: $data,
        );
    }
}
