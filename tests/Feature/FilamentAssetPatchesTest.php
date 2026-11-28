<?php

namespace Tests\Feature;

use App\Support\FilamentAssetPatches;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class FilamentAssetPatchesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The isolated test stack provides writable tmpfs storage, never the checkout.
        $this->app->usePublicPath(storage_path('framework/testing/filament-assets'));
    }

    public function test_publishing_reapplies_compatibility_fixes_and_is_repeatable(): void
    {
        $this->assertSame(0, Artisan::call('filament:assets'));
        $first = $this->assetHashes();

        foreach (array_keys($first) as $asset) {
            $path = public_path('js/filament/'.$asset);
            clearstatcache(true, $path);
            $this->assertSame(0, fileperms($path) & 0111);
        }

        FilamentAssetPatches::apply();
        $this->assertSame($first, $this->assetHashes());

        // Composer invokes this wrapper, which publishes the upstream files again.
        $this->assertSame(0, Artisan::call('filament:upgrade'));
        $this->assertSame($first, $this->assetHashes());
        $this->assertNotSame(
            hash_file('sha256', base_path('vendor/filament/notifications/dist/index.js')),
            $first['notifications/notifications.js'],
        );
    }

    public function test_unexpected_distribution_is_rejected_before_other_assets_are_written(): void
    {
        $this->assertSame(0, Artisan::call('filament:assets'));
        File::put(public_path('js/filament/tables/tables.js'), 'unexpected distribution');
        $before = $this->assetHashes();

        try {
            FilamentAssetPatches::apply();
            $this->fail('An unrecognized asset must require review.');
        } catch (RuntimeException $exception) {
            $this->assertSame('An admin asset compatibility fix no longer matches. Review the dependency update before publishing.', $exception->getMessage());
        }

        $this->assertSame($before, $this->assetHashes());
    }

    private function assetHashes(): array
    {
        $hashes = [];

        foreach (['filament/app.js', 'support/support.js', 'tables/tables.js', 'notifications/notifications.js'] as $asset) {
            $hashes[$asset] = hash_file('sha256', public_path('js/filament/'.$asset));
        }

        return $hashes;
    }
}
