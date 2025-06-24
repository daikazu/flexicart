<?php

declare(strict_types=1);

namespace Daikazu\Flexicart;

use Daikazu\Flexicart\Console\Commands\CleanupCartsCommand;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\StorageInterface;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Storage\DatabaseStorage;
use Daikazu\Flexicart\Storage\SessionStorage;
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
        $this->app->singleton(StorageInterface::class, function ($app) {
            // Check if a custom storage class is specified
            $customStorageClass = $app['config']->get('flexicart.storage_class');

            if ($customStorageClass !== null && class_exists($customStorageClass)) {
                // Create an instance of the custom storage class
                return new $customStorageClass;
            }

            // Otherwise, use the built-in storage classes
            $config = $app['config']->get('flexicart.storage', 'session');

            if ($config === 'database') {
                return new DatabaseStorage(new CartModel);
            }

            return new SessionStorage($app['session']);
        });

        // Bind cart implementation
        $this->app->singleton(CartInterface::class, fn ($app) => new Cart($app->make(StorageInterface::class)));

        // Bind cart alias
        $this->app->singleton('cart', fn ($app) => $app->make(CartInterface::class));

    }

    public function packageBooted(): void {}
}
