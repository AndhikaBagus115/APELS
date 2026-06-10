<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * After creating user, assign the selected role (Req 18.3).
     */
    protected function afterCreate(): void
    {
        $role = $this->data['role'] ?? 'mahasiswa';
        $this->record->syncRoles([$role]);
    }
}
