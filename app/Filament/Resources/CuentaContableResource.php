<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\CuentaContableResource\Pages;
use App\Models\CuentaContable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class CuentaContableResource extends Resource
{
    protected static ?string $model = CuentaContable::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Cuentas contables';

    protected static ?string $modelLabel = 'Cuenta contable';

    protected static ?string $pluralModelLabel = 'Cuentas contables';

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin_empresa', 'super_admin']);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('empresa_id', $user?->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la cuenta')
                ->schema([
                    Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(50)
                        ->unique(
                            table: 'cuentas_contables',
                            column: 'codigo',
                            ignorable: fn (?CuentaContable $record) => $record,
                            modifyRuleUsing: function (Unique $rule): Unique {
                                return $rule->where('empresa_id', auth()->user()->getEmpresaActualId());
                            }
                        )
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(4),

                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'activo' => 'Activo',
                            'pasivo' => 'Pasivo',
                            'patrimonio' => 'Patrimonio',
                            'ingreso' => 'Ingreso',
                            'gasto' => 'Gasto',
                            'costos' => 'Costos',
                            'orden' => 'Orden',
                        ])
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Forms\Components\Select::make('categoria')
                        ->label('Categoría')
                        ->options([
                            'inventario' => 'Inventario',
                            'ventas' => 'Ventas',
                            'compras' => 'Compras',
                            'iva' => 'IVA',
                            'cartera' => 'Cartera',
                            'proveedores' => 'Proveedores',
                            'salidas' => 'Salidas de mercancía',
                            'otros' => 'Otros',
                        ])
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('activo')
                        ->label('Activa')
                        ->default(true)
                        ->columnSpan(2),

                    Forms\Components\Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(6),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentaContables::route('/'),
            'create' => Pages\CreateCuentaContable::route('/create'),
            'edit' => Pages\EditCuentaContable::route('/{record}/edit'),
        ];
    }
}
