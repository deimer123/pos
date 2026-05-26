<?php

namespace App\Filament\Resources\ActorResource\Pages;

use App\Filament\Resources\ActorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListActors extends ListRecords
{
    protected static string $resource = ActorResource::class;

    protected function getHeaderActions(): array
    {
        return [
             Actions\CreateAction::make()->label('Crear'),
        ];
    }

    protected function applyGlobalSearchToTableQuery(Builder $query): Builder
    {
        $search = $this->getTableSearch();

        if (blank($search)) {
            return $query;
        }

        foreach ($this->extractTableSearchWords($search) as $searchWord) {
            $query->where(function (Builder $query) use ($searchWord) {
                $query
                    ->where('nombre', 'like', "%{$searchWord}%")
                    ->orWhere('razon_social', 'like', "%{$searchWord}%")
                    ->orWhere('identificacion', 'like', "%{$searchWord}%")
                    ->orWhere('email', 'like', "%{$searchWord}%")
                    ->orWhere('telefono', 'like', "%{$searchWord}%");
            });
        }

        return $query;
    }
}
