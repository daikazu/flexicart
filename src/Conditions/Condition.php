<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions;

use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use ReflectionClass;

abstract class Condition implements ConditionInterface
{
    public ConditionType $type;
    public ConditionTarget $target;

    public function __construct(
        public readonly string $name,
        public readonly int | float $value,
        ?ConditionTarget $target = null,
        public array | Fluent $attributes = [],
        public int $order = 0,
        public bool $taxable = false
    ) {
        $this->attributes = is_array($attributes) ? fluent($attributes) : $attributes;

        $this->applyPropertyDefaults($target);

    }

    /**
     * Create a new condition instance from an array of parameters.
     *
     * @param  array  $parameters  Array containing the constructor parameters
     *
     * @phpstan-return static
     *
     * @throws InvalidArgumentException When required parameters are missing or invalid
     */
    public static function make(array $parameters): static
    {
        static::validateParameters($parameters);

        $hasClassTarget = static::hasClassDefaultTarget();

        /** @phpstan-ignore-next-line */
        return new static(
            name: $parameters['name'],
            value: $parameters['value'],
            target: $hasClassTarget ? null : ($parameters['target'] ?? null),
            attributes: $parameters['attributes'] ?? [],
            order: $parameters['order'] ?? 0,
            taxable: $parameters['taxable'] ?? false
        );

    }

    /**
     * Validate the parameters array for the make method.
     *
     * @param  array  $parameters  Array containing the constructor parameters
     *
     * @throws InvalidArgumentException When required parameters are missing or invalid
     */
    protected static function validateParameters(array $parameters): void
    {
        // Validate required parameters
        if (empty($parameters['name']) || ! is_string($parameters['name'])) {
            throw new InvalidArgumentException('Parameter "name" is required and must be a non-empty string.');
        }

        if (! isset($parameters['value']) || (! is_int($parameters['value']) && ! is_float($parameters['value']))) {
            throw new InvalidArgumentException('Parameter "value" is required and must be a number (int or float).');
        }

        // Validate target parameter if provided
        if (isset($parameters['target'])) {
            if (! ($parameters['target'] instanceof ConditionTarget)) {
                throw new InvalidArgumentException('Parameter "target" must be an instance of ConditionTarget enum.');
            }
        }

        // Validate optional parameters
        if (isset($parameters['attributes']) && ! is_array($parameters['attributes']) && ! ($parameters['attributes'] instanceof Fluent)) {
            throw new InvalidArgumentException('Parameter "attributes" must be an array or Fluent instance.');
        }

        if (isset($parameters['order']) && ! is_int($parameters['order'])) {
            throw new InvalidArgumentException('Parameter "order" must be an integer.');
        }

        if (isset($parameters['taxable']) && ! is_bool($parameters['taxable'])) {
            throw new InvalidArgumentException('Parameter "taxable" must be a boolean.');
        }
    }

    /**
     * Check if the class has a default target property defined.
     */
    protected static function hasClassDefaultTarget(): bool
    {
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getDefaultProperties();

        return isset($properties['target']);
    }

    /**
     * Convert the condition to an array representation.
     */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'value'      => $this->value,
            'type'       => $this->type->value,
            'target'     => $this->target->value,
            'attributes' => $this->attributes instanceof Fluent ? $this->attributes->toArray() : $this->attributes,
            'order'      => $this->order,
            'taxable'    => $this->taxable,
        ];
    }

    /**
     * Apply property priority logic: class-level properties take precedence over constructor parameters.
     */
    private function applyPropertyDefaults(?ConditionTarget $constructorTarget): void
    {
        // For target: if not set at class level, use constructor parameter or default
        if (! isset($this->target)) {
            $this->target = $constructorTarget ?? ConditionTarget::SUBTOTAL;
        }
    }
}
