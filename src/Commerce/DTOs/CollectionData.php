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
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'] ?? null,
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? true,
            parentId: $data['parent_id'] ?? null,
            children: $data['children'] ?? [],
            products: $data['products'] ?? [],
            raw: $data,
        );
    }
}
