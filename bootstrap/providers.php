<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FilamentAssetPatchServiceProvider;

return [
    AppServiceProvider::class,
    FilamentAssetPatchServiceProvider::class,
    AdminPanelProvider::class,
];
