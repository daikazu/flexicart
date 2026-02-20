<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Console\Commands\CleanupCartsCommand;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Storage\DatabaseStorage;
use Daikazu\Flexicart\Storage\SessionStorage;
use Illuminate\Foundation\Application;
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

        // Bind CommerceClient when enabled
        if ($this->app['config']['flexicart.commerce.enabled'] ?? false) {
            $this->app->singleton(CommerceClient::class, function (Application $app): CommerceClient {
                /** @var \Illuminate\Config\Repository $config */
                $config = $app['config'];

                return new CommerceClient(
                    baseUrl: (string) $config->get('flexicart.commerce.base_url', ''),
                    token: (string) $config->get('flexicart.commerce.token', ''),
                    timeout: (int) $config->get('flexicart.commerce.timeout', 10),
                    cacheEnabled: (bool) $config->get('flexicart.commerce.cache.enabled', true),
                    cacheTtl: (int) $config->get('flexicart.commerce.cache.ttl', 300),
                );
            });
        }

    }

    public function packageBooted(): void {}
}
