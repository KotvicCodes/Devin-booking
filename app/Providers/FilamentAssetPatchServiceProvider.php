<?php

namespace App\Providers;

use App\Support\PublishFilamentAssetsCommand;
use Filament\Support\Commands\AssetsCommand;
use Illuminate\Support\ServiceProvider;

class FilamentAssetPatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Covers both direct asset publishing and filament:upgrade during Composer installs.
        $this->app->bind(AssetsCommand::class, PublishFilamentAssetsCommand::class);
    }
}
