<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GastoResource\Pages;
use App\Models\Gasto;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GastoResource extends Resource
{
    protected static ?string $model = Gasto::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Caja';

    protected static ?string $navigationLabel = 'Movimientos de caja';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Movimiento de caja';

    protected static ?string $pluralModelLabel = 'Movimientos de caja';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['usuario', 'caja'])
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Movimiento')
                ->description('Registra entradas y salidas que afectan la caja o quedan como soporte administrativo.')
                ->icon('heroicon-o-arrows-right-left')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'salida' => 'Salida de efectivo / gasto',
                                'entrada' => 'Entrada de efectivo',
                            ])
                            ->default('salida')
                            ->native(false)
                            ->required(),

                        Select::make('categoria')
                            ->options(static::categorias())
                            ->searchable()
                            ->native(false)
                            ->required(),

                        TextInput::make('descripcion')
                            ->placeholder('Ej: moto carguero, compra de bolsas, base adicional')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('monto')
                            ->numeric()
                            ->prefix('$')
                            ->mask('999.999.999')
                            ->stripCharacters('.')
                            ->required(),

                        DatePicker::make('fecha')
                            ->default(now())
                            ->required(),

                        Select::make('metodo_pago')
                            ->label('Metodo pago')
                            ->options(static::metodosPago())
                            ->native(false)
                            ->required(),
                    ]),

                    Textarea::make('observacion')
                        ->placeholder('Detalle corto del movimiento o soporte interno')
                        ->columnSpanFull()
                        ->rows(3)
                        ->required(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->columns([
                TextColumn::make('id_gasto')
                    ->label('#')
                    ->sortable(),

                BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state === 'entrada' ? 'Entrada' : 'Salida')
                    ->colors([
                        'success' => 'entrada',
                        'danger' => 'salida',
                    ]),

                TextColumn::make('fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                BadgeColumn::make('categoria')
                    ->colors([
                        'success' => 'Base adicional',
                        'warning' => 'Retiro de efectivo',
                        'danger' => 'Arriendo',
                        'primary' => 'Transporte',
                    ]),

                TextColumn::make('descripcion')
                    ->searchable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('metodo_pago')
                    ->label('Metodo'),

                TextColumn::make('usuario.name')
                    ->label('Registrado por')
                    ->placeholder('-'),

                TextColumn::make('caja_id')
                    ->label('Caja')
                    ->placeholder('Administrativo'),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta')
                            ->default(now()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['fecha_desde'] ?? null, fn ($query, $date) => $query->whereDate('fecha', '>=', $date))
                        ->when($data['fecha_hasta'] ?? null, fn ($query, $date) => $query->whereDate('fecha', '<=', $date))),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                    ]),

                Tables\Filters\SelectFilter::make('categoria')
                    ->options(static::categorias())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGastos::route('/'),
            'create' => Pages\CreateGasto::route('/create'),
            'edit' => Pages\EditGasto::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function categorias(): array
    {
        return [
            'Base adicional' => 'Base adicional',
            'Retiro de efectivo' => 'Retiro de efectivo',
            'Luz' => 'Luz',
            'Internet' => 'Internet',
            'Arriendo' => 'Arriendo',
            'Transporte' => 'Transporte',
            'Empleados' => 'Empleados',
            'Papeleria' => 'Papeleria',
            'Impuestos' => 'Impuestos',
            'Otros' => 'Otros',
        ];
    }

    public static function metodosPago(): array
    {
        return [
            'Efectivo' => 'Efectivo',
            'Transferencia' => 'Transferencia',
            'Nequi' => 'Nequi',
            'Otro' => 'Otro',
        ];
    }
}
