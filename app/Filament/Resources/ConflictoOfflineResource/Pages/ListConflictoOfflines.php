<?php

namespace App\Filament\Resources\ConflictoOfflineResource\Pages;

use App\Filament\Resources\ConflictoOfflineResource;
use Filament\Resources\Pages\ListRecords;

class ListConflictoOfflines extends ListRecords
{
    protected static string $resource = ConflictoOfflineResource::class;

    public function getTitle(): string
    {
        return 'Cola Offline';
    }

    public function getBreadcrumb(): string
    {
        return 'Cola Offline';
    }
}
