<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledNotPaid;
use App\Mail\PaymentWarning;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Default pending_window is 14 days (database/settings). */
class ProcessPendingBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_cancels_unpaid_booking_after_pending_window(): void
    {
        $booking = $this->pendingBookingCreatedDaysAgo(15);

        $this->artisan('bookings:process-pending')->assertSuccessful();

        $this->assertSame('cancelled', $booking->fresh()->status);
        Mail::assertQueued(BookingCancelledNotPaid::class);
    }

    public function test_sends_three_day_warning_once(): void
    {
        $booking = $this->pendingBookingCreatedDaysAgo(11);

        $this->artisan('bookings:process-pending')->assertSuccessful();
        $this->artisan('bookings:process-pending')->assertSuccessful();

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->last_warning_at);
        Mail::assertQueuedCount(1);
        Mail::assertQueued(PaymentWarning::class);
    }

    public function test_leaves_fresh_booking_alone(): void
    {
        $booking = $this->pendingBookingCreatedDaysAgo(1);

        $this->artisan('bookings:process-pending')->assertSuccessful();

        $this->assertSame('pending', $booking->fresh()->status);
        Mail::assertNothingQueued();
    }

    private function pendingBookingCreatedDaysAgo(int $days): Booking
    {
        $this->travel(-$days)->days();
        $booking = Booking::create([
            'start_date' => today()->addDays(60),
            'end_date' => today()->addDays(62),
            'customer_name' => 'Test Guest',
            'customer_email' => 'guest@example.com',
            'graduate_count' => 2,
        ]);
        $this->travelBack();
        Mail::fake(); // drop the confirmation e-mail queued on creation

        return $booking;
    }
}
