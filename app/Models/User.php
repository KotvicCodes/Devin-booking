<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /**
     * Gate access to the Filament admin panel.
     *
     * Only accounts that hold at least one capability may enter. A user with
     * every permission flag disabled (and not a super admin) is denied, so a
     * de-provisioned or zero-permission account cannot reach the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_super_admin
            || $this->can_manage_users
            || $this->can_view_bookings
            || $this->can_edit_bookings
            || $this->can_manage_financials;
    }

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_super_admin' => false,
        'can_manage_users' => false,
        'can_view_bookings' => false,
        'can_edit_bookings' => false,
        'can_manage_financials' => false,
    ];


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'can_manage_users',
        'can_view_bookings',
        'can_edit_bookings',
        'can_manage_financials',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_view_bookings' => 'boolean',
        'can_edit_bookings' => 'boolean',
        'can_manage_financials' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
