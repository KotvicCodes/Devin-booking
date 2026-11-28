<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralSettings as SettingsPage;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Livewire\BookingCalendarWidget;
use App\Models\Booking;
use App\Models\ProcessedTransaction;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Saade\FilamentFullCalendar\Data\FetchInfo;
use Tests\TestCase;

class DependencyCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_custom_admin_pages_and_populated_transaction_table_render(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'can_manage_financials' => true]);
        $this->actingAs($admin);
        $booking = $this->makeBooking();
        ProcessedTransaction::create([
            'transaction_id' => 'test-transaction',
            'booking_id' => $booking->id,
            'variable_symbol' => $booking->variable_symbol,
            'amount' => 100,
            'status' => 'processed',
        ]);

        foreach ([
            '/admin/calendar',
            '/admin/general-settings',
            '/admin/system-documentation',
            '/admin/system-health-dashboard',
            '/admin/processed-transactions',
            '/admin/users/create',
            "/admin/users/{$admin->id}/edit",
            "/admin/bookings/{$booking->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_booking_and_record_deposit_through_livewire_forms(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'customer_name' => 'Test Guest',
                'start_date' => today()->addDays(30)->toDateString(),
                'end_date' => today()->addDays(32)->toDateString(),
                'graduate_count' => 2,
                'status' => 'pending',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::sole();
        $this->assertSame(6400, $booking->total_price);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm(['paid_amount' => $booking->deposit_amount])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('deposit_paid', $booking->fresh()->status);
    }

    public function test_user_manager_cannot_create_super_admin_through_livewire_form(): void
    {
        $this->actingAs(User::factory()->create(['can_manage_users' => true]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test Administrator',
                'email' => 'admin@example.com',
                'password' => 'test-password-123',
                'can_view_bookings' => true,
            ])
            ->set('data.is_super_admin', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'admin@example.com')->sole();
        $this->assertFalse($user->is_super_admin);
        $this->assertTrue($user->can_view_bookings);
        $this->assertTrue(Hash::check('test-password-123', $user->password));
    }

    public function test_finance_admin_can_save_settings(): void
    {
        $this->actingAs(User::factory()->create(['can_manage_financials' => true]));

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'bed_capacity' => 24,
                'bank_account_number' => '123456789',
                'bank_code' => '2010',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(24, app(GeneralSettings::class)->bed_capacity);
    }

    public function test_booking_viewer_cannot_save_settings(): void
    {
        $this->actingAs(User::factory()->create(['can_view_bookings' => true]));

        Livewire::test(SettingsPage::class)
            ->set('data.bed_capacity', 1)
            ->call('save');

        $this->assertSame(30, app(GeneralSettings::class)->bed_capacity);
    }

    public function test_calendar_fetches_events_with_new_range_object(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $booking = $this->makeBooking();
        $this->makeBooking([
            'start_date' => today()->addDays(90),
            'end_date' => today()->addDays(92),
        ]);

        $events = app(BookingCalendarWidget::class)->fetchEvents(FetchInfo::fromArray([
            'start' => today()->addDays(29)->toDateString(),
            'end' => today()->addDays(33)->toDateString(),
        ], 'Europe/Prague'));

        $this->assertSame([$booking->id], array_column($events, 'id'));
        $this->assertSame($booking->start_date->toDateString(), $events[0]['start']);
        $this->assertSame(url("/admin/bookings/{$booking->id}/edit"), $events[0]['url']);
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create($attributes + [
            'customer_name' => 'Test Guest',
            'start_date' => today()->addDays(30),
            'end_date' => today()->addDays(32),
            'graduate_count' => 2,
        ]);
    }
}
