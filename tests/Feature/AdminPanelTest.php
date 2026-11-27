<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_user_without_any_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_admin_can_open_main_pages(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        foreach (['/admin', '/admin/bookings', '/admin/bookings/create', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_booking_viewer_cannot_open_user_management(): void
    {
        $this->actingAs(User::factory()->create(['can_view_bookings' => true]));

        $this->get('/admin/bookings')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    }
}
