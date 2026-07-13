<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MesaResource\Pages;
use App\Models\Mesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MesaResource extends Resource
{
    protected static ?string $model = Mesa::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = '🪑 Mesas';
    protected static ?string $navigationLabel = 'Mesas';
    protected static ?string $modelLabel = 'Mesa';
    protected static ?string $pluralModelLabel = 'Mesas';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin_empresa')) {
            return false;
        }
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();
        return (bool) ($config?->usa_mesas ?? false);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin_empresa')) {
            return false;
        }
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();
        return (bool) ($config?->usa_mesas ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')->default($empresaId),

            Forms\Components\Section::make('Datos de la mesa')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\TextInput::make('codigo')
                                ->label('Código')
                                ->required()
                                ->maxLength(20)
                                ->placeholder('M1, B2, T3...'),

                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('Mesa 1, Barra 2, Terraza 3...'),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Forms\Components\TextInput::make('zona')
                                ->label('Zona')
                                ->maxLength(100)
                                ->placeholder('Salón principal, Terraza, Barra...'),

                            Forms\Components\TextInput::make('capacidad')
                                ->label('Capacidad (personas)')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(50),
                        ]),

                    Forms\Components\Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\Toggle::make('activo')
                                ->label('Activa')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('zona')
                    ->label('Zona')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('capacidad')
                    ->label('Capacidad')
                    ->placeholder('—')
                    ->suffix(' pax'),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'success' => 'libre',
                        'danger'  => 'ocupada',
                        'warning' => 'reservada',
                        'gray'    => 'cerrada',
                    ]),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zona')->label('Zona')->options(
                    fn () => Mesa::where('empresa_id', auth()->user()->getEmpresaActualId())
                        ->whereNotNull('zona')->pluck('zona', 'zona')->toArray()
                ),
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('codigo');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMesas::route('/'),
            'create' => Pages\CreateMesa::route('/create'),
            'edit'   => Pages\EditMesa::route('/{record}/edit'),
        ];
    }
}
