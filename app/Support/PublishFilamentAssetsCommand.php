<?php

namespace App\Support;

use Filament\Support\Commands\AssetsCommand;
use Illuminate\Filesystem\Filesystem;

class PublishFilamentAssetsCommand extends AssetsCommand
{
    public function handle(Filesystem $filesystem): int
    {
        $exitCode = parent::handle($filesystem);

        if ($exitCode === self::SUCCESS) {
            FilamentAssetPatches::apply();
        }

        return $exitCode;
    }
}
