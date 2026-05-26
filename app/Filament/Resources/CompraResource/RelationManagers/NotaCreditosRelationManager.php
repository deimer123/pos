<?php

namespace App\Filament\Resources\CompraResource\RelationManagers;

use App\Models\NotaCredito;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class NotaCreditosRelationManager extends RelationManager
{
    protected static string $relationship = 'notasCredito';

    protected static ?string $title = 'Notas crédito';

    /** 🔥 OCULTAR si la compra no está confirmada */
    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return $ownerRecord->estado === 'confirmada';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // para que no aparezca nunca en el sidebar
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('COP', true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (NotaCredito $record) => route('notas-credito.pdf', ['nota' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
