<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Prevent privilege escalation: only an existing super admin may create a
     * super-admin account. For anyone else the flag is forced off regardless of
     * what the (hidden) form field or a crafted request submitted.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->is_super_admin) {
            $data['is_super_admin'] = false;
        }

        return $data;
    }
}
