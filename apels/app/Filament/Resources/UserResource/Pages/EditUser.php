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
     * After saving, sync role to match form selection (Req 18.3).
     */
    protected function afterSave(): void
    {
        $role = $this->data['role'] ?? null;
        if ($role) {
            $this->record->syncRoles([$role]);
        }
    }
}
