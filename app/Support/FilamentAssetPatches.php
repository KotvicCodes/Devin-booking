<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** Compatibility fixes for the reviewed Filament 5.12.0 distribution. */
final class FilamentAssetPatches
{
    public static function apply(): void
    {
        $files = app(Filesystem::class);
        $updates = [];

        // Validate every patch before writing. A changed upstream bundle needs review.
        foreach (self::patches() as $relativePath => $replacements) {
            $path = public_path($relativePath);
            $contents = $files->get($path);

            foreach ($replacements as [$before, $after]) {
                if (substr_count($contents, $before) === 1) {
                    $contents = str_replace($before, $after, $contents);
                } elseif (substr_count($contents, $after) !== 1) {
                    throw new RuntimeException('An admin asset compatibility fix no longer matches. Review the dependency update before publishing.');
                }
            }

            $updates[$path] = $contents;
        }

        foreach ($updates as $path => $contents) {
            $files->replace($path, $contents);
        }
    }

    private static function patches(): array
    {
        return [
            'js/filament/filament/app.js' => [
                [
                    'this.isMobile?this.isOpen&&(this.isOpenDesktop=!0,this.close()):this.isOpen=this.isOpenDesktop',
                    'this.isMobile?this.isOpen&&this.close():this.isOpen=this.isOpenDesktop',
                ],
            ],
            'js/filament/support/support.js' => [
                [
                    'closeQuietly(){this.isOpen=!1}',
                    'closeQuietly(){this.isOpen=!1,this.isTrapActive=!1,this.releaseScrollLock()}',
                ],
            ],
            'js/filament/tables/tables.js' => [
                [
                    'if(!n||c){this.isLoading=!0,this.selectedRecords=new Set(await l.getAllSelectableTableRecordKeys()),this.updatedSelectedRecords(),this.isLoading=!1;return}',
                    'if(!n||c){this.isLoading=!0;try{this.selectedRecords=new Set(await l.getAllSelectableTableRecordKeys()),this.updatedSelectedRecords()}finally{this.isLoading=!1}return}',
                ],
                [
                    'let h=d=>{d.key==="Escape"&&f(d)};',
                    'let h=d=>{d.key==="Escape"&&(this.areFiltersOpen=!1,this.cleanUpFiltersDropdown?.(),this.cleanUpFiltersDropdown=null)};',
                ],
            ],
            'js/filament/notifications/notifications.js' => [
                [
                    'function g(){if(A)return;',
                    <<<'JS'
function retryNotificationPull(pull){
    if(f!==pull)return;
    f=null;
    if(pull.retries>=2)return;
    const generation=d;
    setTimeout(()=>{if(generation===d)g(pull.retries+1)},250*2**pull.retries);
}
function g(retries=0){if(A)return;
JS,
                ],
                [
                    'let s={host:t,batches:e,wake:w};f=s;try{t.$wire.pullNotifications(e).then(()=>p(s),()=>p(s))}catch(n){throw p(s),n}',
                    'let s={host:t,batches:e,wake:w,retries};f=s;try{t.$wire.pullNotifications(e).then(()=>p(s),()=>retryNotificationPull(s))}catch(n){retryNotificationPull(s)}',
                ],
                ['f&&p(f)', 'f&&retryNotificationPull(f)'],
                ['f?.host===i&&p(f)', 'f?.host===i&&retryNotificationPull(f)'],
            ],
        ];
    }
}
