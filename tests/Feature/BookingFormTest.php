<?php

namespace Tests\Feature;

use App\Livewire\BookingForm;
use App\Mail\BookingCreatedConfirmation;
use App\Models\Booking;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke tests for the public reservation flow. Default settings come from
 * database/settings: graduate 100, dog 50, wood 3000 Kč/night, 20 % deposit, 30 beds.
 */
class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_valid_submission_creates_pending_booking_with_price_deposit_and_confirmation(): void
    {
        $this->submit(['graduate_count' => 2, 'dog_count' => 1])
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $booking = Booking::sole();

        $this->assertSame('pending', $booking->status);
        // (2 × 100 + 1 × 50 + 3000 wood) × 2 nights
        $this->assertSame(6500, $booking->total_price);
        $this->assertEquals(1300, $booking->deposit_amount);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $booking->variable_symbol);
        $this->assertTrue($booking->enforce_payment_deadline);

        Mail::assertQueued(BookingCreatedConfirmation::class, fn ($mail) => $mail->hasTo('guest@example.com'));
    }

    public function test_requires_consents_and_at_least_one_guest(): void
    {
        $this->submit(['consent_privacy' => false])->assertHasErrors('consent_privacy');
        $this->submit(['graduate_count' => 0])->assertHasErrors('graduate_count');

        $this->assertSame(0, Booking::count());
    }

    public function test_rejects_dates_that_exceed_bed_capacity(): void
    {
        app(GeneralSettings::class)->fill(['bed_capacity' => 4])->save();
        $this->existingBooking(['graduate_count' => 3]);

        $this->submit(['graduate_count' => 2])->assertHasErrors('start_date');

        $this->assertSame(1, Booking::count());
    }

    public function test_dogs_do_not_count_toward_bed_capacity(): void
    {
        app(GeneralSettings::class)->fill(['bed_capacity' => 4])->save();
        $this->existingBooking(['graduate_count' => 3]);

        $this->submit(['graduate_count' => 1, 'dog_count' => 2])->assertHasNoErrors();

        $this->assertSame(2, Booking::count());
    }

    public function test_rejects_dates_overlapping_a_whole_cabin_booking(): void
    {
        $this->existingBooking(['graduate_count' => 1, 'reserve_whole' => true]);

        $this->submit(['graduate_count' => 1])->assertHasErrors('start_date');

        $this->assertSame(1, Booking::count());
    }

    private function submit(array $overrides = [])
    {
        $fields = $overrides + [
            'start_date' => today()->addDays(30)->toDateString(),
            'end_date' => today()->addDays(32)->toDateString(),
            'graduate_count' => 1,
            'customer_name' => 'Jan Novák',
            'customer_email' => 'guest@example.com',
            'consent_acknowledgement' => true,
            'consent_privacy' => true,
            'consent_house_rules' => true,
        ];

        $component = Livewire::test(BookingForm::class);
        foreach ($fields as $name => $value) {
            $component->set($name, $value);
        }

        return $component->call('submitReservation');
    }

    private function existingBooking(array $attributes): Booking
    {
        return Booking::create($attributes + [
            'start_date' => today()->addDays(30),
            'end_date' => today()->addDays(32),
            'customer_name' => 'Existing Guest',
            'customer_email' => 'existing@example.com',
        ]);
    }
}
