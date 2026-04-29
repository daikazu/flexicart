<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Console\Commands\CleanupCartsCommand;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\CommerceClientInterface;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Storage\DatabaseStorage;
use Daikazu\Flexicart\Storage\SessionStorage;
use Illuminate\Foundation\Application;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CartServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('flexicart')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_cart_table')
            ->hasCommand(CleanupCartsCommand::class);

    }

    public function packageRegistered(): void
    {

        // Bind storage implementation
        $this->app->singleton(StorageInterface::class, function (Application $app): StorageInterface {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app['config'];

            // Check if a custom storage class is specified
            $customStorageClass = $config->get('flexicart.storage_class');

            if (is_string($customStorageClass) && class_exists($customStorageClass)) {
                /** @var StorageInterface */
                return new $customStorageClass;
            }

            // Otherwise, use the built-in storage classes
            $storageType = $config->get('flexicart.storage', 'session');

            if ($storageType === 'database') {
                return new DatabaseStorage(new CartModel);
            }

            /** @var \Illuminate\Session\SessionManager $session */
            $session = $app['session'];

            return new SessionStorage($session);
        });

        // Bind cart implementation
        $this->app->singleton(CartInterface::class, fn (Application $app): Cart => new Cart($app->make(StorageInterface::class)));

        // Bind cart alias
        $this->app->singleton('cart', fn (Application $app): CartInterface => $app->make(CartInterface::class));

        // Bind commerce driver when enabled
        /** @var \Illuminate\Config\Repository $appConfig */
        $appConfig = $this->app->make('config');
        if ($appConfig->get('flexicart.commerce.enabled', false)) {
            $driverValue = $appConfig->get('flexicart.commerce.driver', 'auto');
            $driver = is_string($driverValue) ? $driverValue : 'auto';
            $useLocal = $driver === 'local' || ($driver === 'auto' && $this->flexiCommerceInstalled());

            $this->app->singleton(CommerceClientInterface::class, function (Application $app) use ($useLocal): CommerceClientInterface {
                if ($useLocal) {
                    if (! $this->flexiCommerceInstalled()) {
                        throw new RuntimeException(
                            'Cannot use local commerce driver: flexi-commerce package is not installed.'
                        );
                    }

                    /** @var \Illuminate\Config\Repository $config */
                    $config = $app->make('config');
                    $storeIdValue = $config->get('flexicart.commerce.store_id');

                    return new \Daikazu\Flexicart\Commerce\LocalCommerceDriver(
                        storeId: is_string($storeIdValue) ? $storeIdValue : null,
                    );
                }

                /** @var \Illuminate\Config\Repository $config */
                $config = $app->make('config');
                $storeIdValue = $config->get('flexicart.commerce.store_id');
                $baseUrlValue = $config->get('flexicart.commerce.base_url', '');
                $tokenValue = $config->get('flexicart.commerce.token', '');
                $timeoutValue = $config->get('flexicart.commerce.timeout', 10);
                $cacheTtlValue = $config->get('flexicart.commerce.cache.ttl', 300);

                return new CommerceClient(
                    baseUrl: is_string($baseUrlValue) ? $baseUrlValue : '',
                    token: is_string($tokenValue) ? $tokenValue : '',
                    storeId: is_string($storeIdValue) ? $storeIdValue : null,
                    timeout: is_int($timeoutValue) ? $timeoutValue : 10,
                    cacheEnabled: (bool) $config->get('flexicart.commerce.cache.enabled', true),
                    cacheTtl: is_int($cacheTtlValue) ? $cacheTtlValue : 300,
                );
            });

            // Register concrete alias only for the active driver
            if ($useLocal) {
                $this->app->alias(CommerceClientInterface::class, \Daikazu\Flexicart\Commerce\LocalCommerceDriver::class);
            } else {
                $this->app->alias(CommerceClientInterface::class, CommerceClient::class);
            }
        }

    }

    public function packageBooted(): void {}

    private function flexiCommerceInstalled(): bool
    {
        return class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class);
    }
}
