<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use InvalidArgumentException;

final class MergeStrategyFactory
{
    /**
     * @var array<string, class-string<MergeStrategyInterface>>
     */
    private static array $strategies = [
        'sum' => SumMergeStrategy::class,
        'replace' => ReplaceMergeStrategy::class,
        'max' => MaxMergeStrategy::class,
        'keep_target' => KeepTargetMergeStrategy::class,
    ];

    /**
     * Create a merge strategy instance by name.
     *
     * @throws InvalidArgumentException
     */
    public static function make(string $strategy): MergeStrategyInterface
    {
        if (! isset(self::$strategies[$strategy])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid merge strategy "%s". Available strategies: %s',
                    $strategy,
                    implode(', ', array_keys(self::$strategies))
                )
            );
        }

        return new self::$strategies[$strategy]();
    }

    /**
     * Get the default merge strategy.
     */
    public static function default(): MergeStrategyInterface
    {
        /** @var string $defaultStrategy */
        $defaultStrategy = config('flexicart.merge.default_strategy', 'sum');

        return self::make($defaultStrategy);
    }

    /**
     * Get available strategy names.
     *
     * @return array<string>
     */
    public static function available(): array
    {
        return array_keys(self::$strategies);
    }

    /**
     * Register a custom merge strategy.
     *
     * @param  class-string<MergeStrategyInterface>  $strategyClass
     */
    public static function register(string $name, string $strategyClass): void
    {
        self::$strategies[$name] = $strategyClass;
    }
}
