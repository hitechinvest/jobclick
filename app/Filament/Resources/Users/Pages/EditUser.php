<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())->except(['updated_at', 'password', 'remember_token'])->all();
        if ($changes) {
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'user_update', 'subject_type' => $this->record::class, 'subject_id' => $this->record->id, 'changes' => $changes]);
        }
    }
}
