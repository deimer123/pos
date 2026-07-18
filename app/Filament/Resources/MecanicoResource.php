<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MecanicoResource\Pages;
use App\Models\Mecanico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MecanicoResource extends Resource
{
    protected static ?string $model = Mecanico::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Mecánicos';

    protected static ?string $modelLabel = 'Mecánico';

    protected static ?string $pluralModelLabel = 'Mecánicos';

    protected static ?string $navigationGroup = 'Taller';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        if (! auth()->check() || ! auth()->user()->hasAnyRole(['admin_empresa', 'digitador'])) {
            return false;
        }

        if (! auth()->user()->puedeVerResource('mecanicos')) {
            return false;
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        return (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_taller');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del mecánico')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre completo')
                                ->required()
                                ->maxLength(150),

                            Forms\Components\TextInput::make('cedula')
                                ->label('Cédula')
                                ->maxLength(20),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Forms\Components\TextInput::make('telefono')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(20),

                            Forms\Components\Toggle::make('activo')
                                ->label('Activo')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->query(Mecanico::query()->where('empresa_id', $empresaId))
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cédula')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('servicios_count')
                    ->label('Servicios asignados')
                    ->counts('servicios')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMecanicos::route('/'),
            'create' => Pages\CreateMecanico::route('/create'),
            'edit'   => Pages\EditMecanico::route('/{record}/edit'),
        ];
    }
}
