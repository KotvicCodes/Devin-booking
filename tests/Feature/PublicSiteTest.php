<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_home_page_renders_booking_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire('booking-form');
    }

    public function test_availability_api_returns_rules_and_active_future_bookings_only(): void
    {
        $active = $this->makeBooking(['start_date' => today()->addDays(10), 'end_date' => today()->addDays(12)]);
        $this->makeBooking(['start_date' => today()->addDays(20), 'end_date' => today()->addDays(22)], status: 'cancelled');
        $this->makeBooking(['start_date' => today()->subDays(10), 'end_date' => today()->subDays(8)]);

        $response = $this->getJson('/api/availability')->assertOk();

        $response->assertJsonStructure([
            'cabin_rules' => ['bed_capacity', 'prices' => ['graduate', 'student', 'child', 'external', 'dog', 'wood'], 'deposit_percentage', 'pending_window'],
            'bookings' => [['id', 'start_date', 'end_date', 'reserved_beds', 'reserve_whole', 'status']],
        ]);
        $this->assertSame([$active->id], array_column($response->json('bookings'), 'id'));
        $this->assertArrayNotHasKey('bank_account_number', $response->json('cabin_rules'));
    }

    private function makeBooking(array $attributes, string $status = 'pending'): Booking
    {
        $booking = Booking::create($attributes + [
            'customer_name' => 'Test Guest',
            'customer_email' => 'guest@example.com',
            'graduate_count' => 2,
        ]);

        if ($status !== $booking->status) {
            $booking->updateQuietly(['status' => $status]);
        }

        return $booking;
    }
}
