<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class CollectionData
{
    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $type,
        public ?string $description,
        public bool $isActive,
        public ?int $parentId,
        public array $children,
        public array $products,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>> $children */
        $children = is_array($data['children'] ?? null) ? $data['children'] : [];
        /** @var array<int, array<string, mixed>> $products */
        $products = is_array($data['products'] ?? null) ? $data['products'] : [];

        $slugRaw = $data['slug'] ?? '';
        $nameRaw = $data['name'] ?? '';
        $typeRaw = $data['type'] ?? null;
        $descriptionRaw = $data['description'] ?? null;
        $isActiveRaw = $data['is_active'] ?? true;
        $parentIdRaw = $data['parent_id'] ?? null;

        return new self(
            slug: is_string($slugRaw) ? $slugRaw : '',
            name: is_string($nameRaw) ? $nameRaw : '',
            type: is_string($typeRaw) ? $typeRaw : null,
            description: is_string($descriptionRaw) ? $descriptionRaw : null,
            isActive: is_bool($isActiveRaw) ? $isActiveRaw : (bool) $isActiveRaw,
            parentId: is_int($parentIdRaw) ? $parentIdRaw : null,
            children: $children,
            products: $products,
            raw: $data,
        );
    }
}
