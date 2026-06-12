<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\TercerosContableExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\TercerosContableResource\Pages;
use App\Models\Actor;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class TercerosContableResource extends Resource
{
    protected static ?string $model = Actor::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Terceros contables';

    protected static ?string $modelLabel = 'Tercero contable';

    protected static ?string $pluralModelLabel = 'Terceros contables';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->query(Actor::query()->where('empresa_id', $empresaId))
            ->filters([
                SelectFilter::make('clasificacion')
                    ->label('Tipo')
                    ->options([
                        'cliente' => 'Cliente',
                        'proveedor' => 'Proveedor',
                        'cliente_proveedor' => 'Cliente / Proveedor',
                    ])
                    ->placeholder('Todos'),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'todos' => 'Todos',
                                'cliente' => 'Cliente',
                                'proveedor' => 'Proveedor',
                            ])
                            ->default('todos')
                            ->required(),
                    ])
                    ->action(fn (array $data) => Excel::download(
                        new TercerosContableExport($empresaId, $data['tipo'] ?? 'todos'),
                        'terceros-contables-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('identificacion')->label('Documento')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('clasificacion')->label('Clasificación')->badge(),
                Tables\Columns\TextColumn::make('telefono')->label('Teléfono')->placeholder('-'),
                Tables\Columns\TextColumn::make('email')->label('Email')->placeholder('-'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTercerosContables::route('/'),
        ];
    }
}
