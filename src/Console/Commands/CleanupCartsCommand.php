<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Console\Commands;

use Daikazu\Flexicart\Models\CartModel;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flexicart:cleanup-carts {--force : Force delete all carts regardless of age}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old cart entries from the database or force delete all carts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if force option is specified
        if ($this->option('force')) {
            try {
                $this->info('Force deleting all carts...');
                $count = CartModel::count();

                // Get all carts and delete them one by one to trigger Eloquent events
                CartModel::all()->each(function ($cart) {
                    $cart->delete();
                });

                $this->info("Successfully deleted all {$count} cart(s).");

                return self::SUCCESS;
            } catch (Exception $e) {
                $this->error("Error deleting all carts: {$e->getMessage()}");
                Log::error("Error deleting all carts: {$e->getMessage()}", [
                    'exception' => $e,
                ]);

                return self::FAILURE;
            }
        }

        // Check if cleanup is enabled
        if (! config('flexicart.cleanup.enabled', true)) {
            $this->info('Cart cleanup is disabled in the configuration.');

            return self::SUCCESS;
        }

        // Get the cart lifetime from config (default to 1 week)
        $lifetime = (int) config('flexicart.cleanup.lifetime', 60 * 24 * 7);

        // Calculate the cutoff date
        $cutoffDate = Carbon::now()->subMinutes($lifetime);

        $this->info("Cleaning up carts older than {$cutoffDate->format('Y-m-d H:i:s')}");

        try {
            // Get carts that haven't been updated for longer than the lifetime
            $oldCarts = CartModel::where('updated_at', '<', $cutoffDate)->get();
            $count = $oldCarts->count();

            // Delete each cart to trigger Eloquent events
            $oldCarts->each(function ($cart) {
                $cart->delete();
            });

            $this->info("Successfully deleted {$count} old cart(s).");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error cleaning up carts: {$e->getMessage()}");
            Log::error("Error cleaning up carts: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return self::FAILURE;
        }
    }
}
