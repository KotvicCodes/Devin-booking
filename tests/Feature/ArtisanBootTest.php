<?php

namespace Tests\Feature;

use App\Services\FioBankApiClient;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArtisanBootTest extends TestCase
{
    /** Regression: a missing FIO_BANK_API_TOKEN used to throw a TypeError and crash all of artisan. */
    public function test_missing_fio_token_means_not_configured_instead_of_crashing(): void
    {
        config(['services.fio_bank.token' => null]);

        $this->assertFalse((new FioBankApiClient)->isConfigured());
        $this->assertSame(0, Artisan::call('list'));
    }
}
