<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Prevent privilege escalation: only an existing super admin may change the
     * super-admin flag. For anyone else it is pinned to the record's stored
     * value, so they can neither grant nor revoke super-admin via a crafted
     * request. (UserPolicy already blocks editing an existing super admin.)
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->is_super_admin) {
            $data['is_super_admin'] = (bool) $this->record->is_super_admin;
        }

        return $data;
    }
}
