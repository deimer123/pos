<?php
namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class EmpresaResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Crear Empresa';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';
    protected static ?string $navigationGroup = 'Administración';

    // Solo visible para SUPER_ADMIN
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Empresa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Empresa')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->validationMessages([
        'unique' => 'Este correo ya existe.',
    ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('direccion')
                            ->label('Dirección')
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Acceso del Administrador')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->same('passwordConfirmation')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                        Forms\Components\TextInput::make('passwordConfirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(false),

                        Forms\Components\Toggle::make('activo')
                            ->label('Empresa Activa')
                            ->default(true),

                        Forms\Components\Hidden::make('tipo_usuario')
                            ->default('empresa'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Plan de activacion')
                    ->schema([
                        Forms\Components\Select::make('plan_meses')
                            ->label('Plan')
                            ->options([
                                3 => '3 meses',
                                6 => '6 meses',
                                9 => '9 meses',
                                12 => '1 año',
                            ])
                            ->required()
                            ->default(3)
                            ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                if (blank($get('plan_ends_at'))) {
                                    $set('plan_ends_at', static::calculatePlanEndDate(
                                        $get('plan_started_at') ?: today()->toDateString(),
                                        (int) ($state ?: 3),
                                    ));
                                }
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $set('plan_ends_at', static::calculatePlanEndDate(
                                    $get('plan_started_at') ?: today()->toDateString(),
                                    (int) ($state ?: 3),
                                ));
                            }),

                        Forms\Components\DatePicker::make('plan_started_at')
                            ->label('Fecha de activacion')
                            ->default(fn () => today()->toDateString())
                            ->required()
                            ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                if (blank($get('plan_ends_at'))) {
                                    $set('plan_ends_at', static::calculatePlanEndDate(
                                        $state ?: today()->toDateString(),
                                        (int) ($get('plan_meses') ?: 3),
                                    ));
                                }
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $set('plan_ends_at', static::calculatePlanEndDate(
                                    $state ?: today()->toDateString(),
                                    (int) ($get('plan_meses') ?: 3),
                                ));
                            }),

                        Forms\Components\DatePicker::make('plan_ends_at')
                            ->label('Vence')
                            ->default(fn (Get $get) => static::calculatePlanEndDate(
                                $get('plan_started_at') ?: today()->toDateString(),
                                (int) ($get('plan_meses') ?: 3),
                            ))
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Al llegar esta fecha, la empresa y sus empleados no podran iniciar sesion.'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Limites de usuarios')
                    ->description('Estos cupos controlan cuantos empleados puede crear cada empresa. Si necesita mas, debe solicitarlo al super admin.')
                    ->schema([
                        Forms\Components\TextInput::make('max_vendedores')
                            ->label('Max. vendedores')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('max_cajeros')
                            ->label('Max. cajeros')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('max_digitadores')
                            ->label('Max. digitadores')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre de la Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('empleados_count')
                    ->label('Empleados')
                    ->formatStateUsing(function ($record) {
                        return $record->empleados()->count();
                    })
                    ->color('primary'),

                Tables\Columns\TextColumn::make('cupos')
                    ->label('Cupos')
                    ->state(fn (User $record) =>
                        "V {$record->max_vendedores} / C {$record->max_cajeros} / D {$record->max_digitadores}"
                    )
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('plan_meses')
                    ->label('Plan')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} meses" : 'Sin plan')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('plan_ends_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (User $record) => $record->planVencido() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('estado_plan')
                    ->label('Acceso')
                    ->state(fn (User $record) => ! $record->activo
                        ? 'Inactiva'
                        : ($record->planVencido() ? 'Vencida' : 'Activa'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Activa' => 'success',
                        'Vencida' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todas las empresas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('activar')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => ! $record->activo || $record->planVencido())
                    ->form([
                        Forms\Components\Select::make('plan_meses')
                            ->label('Nuevo plan')
                            ->options([
                                3 => '3 meses',
                                6 => '6 meses',
                                9 => '9 meses',
                                12 => '1 ano',
                            ])
                            ->required()
                            ->default(3),
                    ])
                    ->action(function (User $record, array $data): void {
                        $meses = (int) $data['plan_meses'];

                        $record->update([
                            'activo' => true,
                            'plan_meses' => $meses,
                            'plan_started_at' => today(),
                            'plan_ends_at' => today()->addMonths($meses),
                        ]);
                    }),

                Tables\Actions\Action::make('desactivar')
                    ->label('Desactivar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => (bool) $record->activo)
                    ->action(fn (User $record) => $record->update(['activo' => false])),

                Tables\Actions\Action::make('eliminar_todo')
                    ->label('Eliminar todo')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar empresa y toda su informacion')
                    ->modalDescription('Esta accion eliminara la empresa, empleados, productos, ventas, compras, inventario, caja y movimientos relacionados. No se puede deshacer.')
                    ->action(fn (User $record) => static::deleteEmpresaData($record))
                    ->successNotificationTitle('Empresa eliminada correctamente'),
            ])
            ->bulkActions([
                
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }

    // Solo mostrar empresas (admin_empresa)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tipo_usuario', 'empresa')
            ->role('admin_empresa');
    }

    // Solo SUPER_ADMIN puede acceder
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function deleteEmpresaData(User $record): void
    {
        DB::transaction(function () use ($record): void {
            $empresaId = $record->id;
            $userIds = User::query()
                ->where('id', $empresaId)
                ->orWhere('empresa_id', $empresaId)
                ->pluck('id');

            $facturaIds = static::idsBy('facturas', 'empresa_id', $empresaId);
            $compraIds = static::idsBy('compras', 'empresa_id', $empresaId);
            $devolucionIds = static::idsBy('devoluciones', 'empresa_id', $empresaId);
            $notaCreditoIds = static::idsWhere('nota_creditos', fn ($query) => $query
                ->where('empresa_id', $empresaId)
                ->orWhereIn('compra_id', $compraIds));
            $devolucionCompraIds = static::idsWhere('devolucion_compras', fn ($query) => $query
                ->where('empresa_id', $empresaId)
                ->orWhereIn('compra_id', $compraIds));
            $ajusteIds = static::idsBy('ajustes_inventario', 'empresa_id', $empresaId);
            $prefacturaIds = static::idsBy('prefacturas', 'empresa_id', $empresaId);
            $productIds = static::idsBy('products', 'empresa_id', $empresaId);

            static::deleteWhereIn('sessions', 'user_id', $userIds);
            static::deleteWhere('model_has_roles', fn ($query) => $query->where('model_type', User::class)->whereIn('model_id', $userIds));
            static::deleteWhere('model_has_permissions', fn ($query) => $query->where('model_type', User::class)->whereIn('model_id', $userIds));

            static::deleteWhereIn('prefactura_productos', 'prefactura_id', $prefacturaIds);
            static::deleteByEmpresa('prefactura_productos', $empresaId);
            static::deleteByEmpresa('prefacturas', $empresaId);

            static::deleteWhereIn('devolucion_detalles', 'devolucion_id', $devolucionIds);
            static::deleteByEmpresa('devoluciones', $empresaId);
            static::deleteWhereIn('factura_pagos', 'factura_id', $facturaIds);
            static::deleteWhereIn('factura_detalles', 'factura_id', $facturaIds);
            static::deleteByEmpresa('facturas', $empresaId);

            static::deleteWhereIn('nota_credito_items', 'nota_credito_id', $notaCreditoIds);
            static::deleteWhereIn('nota_creditos', 'compra_id', $compraIds);
            static::deleteByEmpresa('nota_creditos', $empresaId);
            static::deleteWhereIn('devolucion_compra_detalles', 'devolucion_compra_id', $devolucionCompraIds);
            static::deleteWhereIn('devolucion_compras', 'compra_id', $compraIds);
            static::deleteByEmpresa('devolucion_compras', $empresaId);
            static::deleteWhereIn('compra_pagos', 'compra_id', $compraIds);
            static::deleteWhereIn('pagos', 'compra_id', $compraIds);
            static::deleteByEmpresa('pagos', $empresaId);
            static::deleteWhereIn('compra_detalles', 'compra_id', $compraIds);
            static::deleteByEmpresa('compras', $empresaId);

            static::deleteWhereIn('ajuste_inventario_detalles', 'ajuste_inventario_id', $ajusteIds);
            static::deleteByEmpresa('ajustes_inventario', $empresaId);

            static::deleteByEmpresa('gastos', $empresaId);
            static::deleteByEmpresa('cajas', $empresaId);
            static::deleteByEmpresa('stock_movimientos', $empresaId);
            static::deleteByEmpresa('kardex', $empresaId);

            static::deleteWhereIn('alternate_codes', 'product_id', $productIds);
            static::deleteByEmpresa('alternate_codes', $empresaId);
            static::deleteByEmpresa('products', $empresaId);
            static::deleteByEmpresa('subfamilias', $empresaId);
            static::deleteByEmpresa('familias', $empresaId);
            static::deleteByEmpresa('actors', $empresaId);
            static::deleteByEmpresa('configuracion_empresas', $empresaId);

            User::query()->where('empresa_id', $empresaId)->delete();
            $record->delete();
        });
    }

    public static function calculatePlanEndDate(mixed $startDate, int $months): string
    {
        return Carbon::parse($startDate ?: today())->addMonths($months ?: 3)->toDateString();
    }

    protected static function idsBy(string $table, string $column, mixed $value)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)->where($column, $value)->pluck('id');
    }

    protected static function idsWhere(string $table, callable $callback)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table($table);
        $callback($query);

        return $query->pluck('id');
    }

    protected static function deleteByEmpresa(string $table, int $empresaId): void
    {
        static::deleteWhere($table, fn ($query) => $query->where('empresa_id', $empresaId));
    }

    protected static function deleteWhereIn(string $table, string $column, $values): void
    {
        $values = collect($values)->filter()->values();

        if (! Schema::hasTable($table) || $values->isEmpty()) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }

    protected static function deleteWhere(string $table, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table);
        $callback($query);
        $query->delete();
    }
}
