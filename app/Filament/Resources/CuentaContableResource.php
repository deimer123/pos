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

class CuentaContableResource extends Resource
{
    protected static ?string $model = CuentaContable::class;
    protected static ?string $cluster = Contabilidad::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Cuentas contables';
    protected static ?string $modelLabel = 'Cuenta contable';
    protected static ?string $pluralModelLabel = 'Cuentas contables';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')
                ->default(fn () => auth()->user()->getEmpresaActualId())
                ->dehydrated(),
            Forms\Components\Section::make('Cuenta contable')
                ->schema([
                    Forms\Components\TextInput::make('codigo')
                        ->label('Codigo')
                        ->required()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('tipo')
                        ->label('Tipo')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('categoria')
                        ->label('Categoria')
                        ->maxLength(50),
                    Forms\Components\Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->query(CuentaContable::query()->where('empresa_id', $empresaId))
            ->defaultSort('codigo')
            ->emptyStateHeading('Aun no hay cuentas contables')
            ->emptyStateDescription('Aqui creas el plan contable de tu empresa. Luego podras asignar cada cuenta a productos y reportes del modulo contable.')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->alignCenter(),
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
