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
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        /** @var array<int, array<string, mixed>> $prices */
        $prices = is_array($data['prices'] ?? null) ? $data['prices'] : [];
        /** @var array<int, array<string, mixed>> $priceTiers */
        $priceTiers = is_array($data['price_tiers'] ?? null) ? $data['price_tiers'] : [];
        /** @var array<int, array<string, mixed>> $options */
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        /** @var array<int, array<string, mixed>> $variants */
        $variants = is_array($data['variants'] ?? null) ? $data['variants'] : [];
        /** @var array<int, array<string, mixed>> $addonGroups */
        $addonGroups = is_array($data['addon_groups'] ?? null) ? $data['addon_groups'] : [];

        $slugRaw = $data['slug'] ?? '';
        $nameRaw = $data['name'] ?? '';
        $typeRaw = $data['type'] ?? '';
        $statusRaw = $data['status'] ?? null;
        $descriptionRaw = $data['description'] ?? null;

        return new self(
            slug: is_string($slugRaw) ? $slugRaw : '',
            name: is_string($nameRaw) ? $nameRaw : '',
            type: is_string($typeRaw) ? $typeRaw : '',
            status: is_string($statusRaw) ? $statusRaw : null,
            description: is_string($descriptionRaw) ? $descriptionRaw : null,
            meta: $meta,
            prices: $prices,
            priceTiers: $priceTiers,
            options: $options,
            variants: $variants,
            addonGroups: $addonGroups,
            raw: $data,
        );
    }
}
