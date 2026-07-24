<?php
namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaResource\Pages;
use App\Models\Complemento;
use App\Models\PaqueteUsuarios;
use App\Models\Plan;
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

                Forms\Components\Section::make('Plan comercial')
                    ->description('Los planes, complementos y paquetes de usuarios se administran en los menus "Planes", "Complementos" y "Paquetes de usuarios". Al elegir un plan se autocompletan la duracion y los usuarios incluidos abajo (se pueden ajustar despues si hace falta).')
                    ->schema([
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::where('activo', true)->orderBy('orden')->pluck('nombre', 'id'))
                            ->placeholder('Sin plan (configurar manualmente abajo)')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularPlanComercial($get, $set)),

                        Forms\Components\Select::make('paquete_usuarios_id')
                            ->label('Paquete de usuarios adicionales')
                            ->options(fn () => PaqueteUsuarios::where('activo', true)->orderBy('orden')->pluck('nombre', 'id'))
                            ->placeholder('Ninguno')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularPlanComercial($get, $set)),

                        Forms\Components\CheckboxList::make('complementos_ids')
                            ->label('Complementos')
                            ->options(fn () => Complemento::where('activo', true)->orderBy('orden')->pluck('nombre', 'id'))
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?User $record): void {
                                if ($record) {
                                    $component->state($record->complementos()->pluck('complemento_id')->all());
                                }
                            })
                            ->live()
                            ->columns(2)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('total_plan_preview')
                            ->label('Total a cobrar')
                            ->content(fn (Get $get) => static::totalPlanHtml($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // La duracion/vencimiento del plan ya se define eligiendo un
                // Plan arriba en "Plan comercial" (recalcularPlanComercial()
                // autocompleta estos 3 campos) -- se quita la seccion visible
                // duplicada, pero los campos se siguen guardando ocultos.
                Forms\Components\Hidden::make('plan_meses')
                    ->default(3)
                    ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                        if (blank($get('plan_ends_at'))) {
                            $set('plan_ends_at', static::calculatePlanEndDate(
                                $get('plan_started_at') ?: today()->toDateString(),
                                (int) ($state ?: 3),
                            ));
                        }
                    }),

                Forms\Components\Hidden::make('plan_started_at')
                    ->default(fn () => today()->toDateString())
                    ->afterStateHydrated(function ($state, Set $set, ?\App\Models\User $record): void {
                        if (filled($state)) {
                            return;
                        }

                        $set('plan_started_at', $record?->plan_started_at?->toDateString()
                            ?? $record?->created_at?->toDateString()
                            ?? today()->toDateString());
                    }),

                Forms\Components\Hidden::make('plan_ends_at')
                    ->default(fn (Get $get) => static::calculatePlanEndDate(
                        $get('plan_started_at') ?: today()->toDateString(),
                        (int) ($get('plan_meses') ?: 3),
                    )),

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

                Forms\Components\Section::make('Facturacion electronica')
                    ->description('Activacion y credenciales Factus administradas por el super admin.')
                    ->statePath('factus')
                    ->schema([
                        Forms\Components\Toggle::make('factus_enabled')
                            ->label('Activar facturacion electronica')
                            ->helperText('Cuando este activo, esta empresa podra emitir facturas electronicas usando Factus.')
                            ->live()
                            ->default(false),

                        // Los datos/credenciales de Factus solo tienen sentido si
                        // se activo la facturacion electronica -- si esta apagada
                        // no hay nada que configurar todavia, asi que se ocultan
                        // en vez de solo dejarlos opcionales.
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('nit')
                                ->label('NIT de la empresa')
                                ->maxLength(20)
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                                ->helperText('Obligatorio para activar facturacion electronica y mostrarlo en la factura impresa.'),

                            Forms\Components\Select::make('factus_environment')
                                ->label('Ambiente Factus')
                                ->options([
                                    'sandbox' => 'Pruebas / Sandbox',
                                    'production' => 'Produccion',
                                ])
                                ->default('sandbox')
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\TextInput::make('factus_base_url')
                                ->label('URL API Factus')
                                ->placeholder('Dejar vacio para usar la URL del ambiente seleccionado')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('factus_username')
                                ->label('Usuario Factus')
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                                ->maxLength(255),

                            Forms\Components\TextInput::make('factus_password')
                                ->label('Password Factus')
                                ->password()
                                ->required(fn (Forms\Get $get, ?string $operation): bool => (bool) $get('factus_enabled') && $operation === 'create')
                                ->dehydrated(fn ($state) => filled($state))
                                ->helperText('En edicion puedes dejarlo vacio para conservar el password guardado.'),

                            Forms\Components\TextInput::make('factus_client_id')
                                ->label('Client ID Factus')
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                                ->maxLength(255),

                            Forms\Components\TextInput::make('factus_client_secret')
                                ->label('Client Secret Factus')
                                ->password()
                                ->required(fn (Forms\Get $get, ?string $operation): bool => (bool) $get('factus_enabled') && $operation === 'create')
                                ->dehydrated(fn ($state) => filled($state))
                                ->helperText('En edicion puedes dejarlo vacio para conservar el secreto guardado.'),

                            Forms\Components\TextInput::make('factus_numbering_range_id')
                                ->label('ID Rango Factus')
                                ->numeric()
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                                ->helperText('Se llena con el boton Sincronizar rangos, o puedes escribirlo si Factus te lo entrega.'),

                            Forms\Components\TextInput::make('factus_credit_note_numbering_range_id')
                                ->label('ID Rango Nota Credito Factus')
                                ->numeric()
                                ->helperText('Rango para notas credito electronicas. En Factus corresponde al documento 22; si solo existe un rango activo, Factus puede tomarlo automaticamente.'),

                            Forms\Components\Toggle::make('factus_send_email')
                                ->label('Enviar correo desde Factus')
                                ->default(false),

                            Forms\Components\TextInput::make('prefijo')
                                ->label('Prefijo')
                                ->maxLength(10)
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\TextInput::make('rango_desde')
                                ->label('Rango desde')
                                ->numeric()
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\TextInput::make('rango_hasta')
                                ->label('Rango hasta')
                                ->numeric()
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\TextInput::make('rango_actual')
                                ->label('Rango actual')
                                ->numeric()
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\TextInput::make('numero_resolucion')
                                ->label('Numero de resolucion DIAN')
                                ->maxLength(50)
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                                ->helperText('Numero de la autorizacion de numeracion de facturacion. Debe verse en la representacion impresa.'),

                            Forms\Components\DatePicker::make('fecha_inicio')
                                ->label('Fecha inicio')
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),

                            Forms\Components\DatePicker::make('fecha_fin')
                                ->label('Fecha fin')
                                ->required(fn (Forms\Get $get): bool => (bool) $get('factus_enabled')),
                        ])
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('factus_enabled'))
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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

                Tables\Columns\TextColumn::make('valor_plan_total')
                    ->label('Total plan')
                    ->formatStateUsing(fn ($state) => $state !== null ? '$ ' . number_format((float) $state, 0, ',', '.') : '—')
                    ->toggleable(),

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
            static::deleteWhereIn('prefactura_productos', 'producto_id', $productIds);
            static::deleteByEmpresa('prefactura_productos', $empresaId);
            static::deleteWhereIn('prefactura_producto', 'prefactura_id', $prefacturaIds);
            static::deleteWhereIn('prefactura_producto', 'producto_id', $productIds);
            static::deleteByEmpresa('prefactura_producto', $empresaId);
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

    // Al elegir un plan o un paquete de usuarios adicionales, autocompleta
    // la duracion (plan_meses/plan_ends_at) y el cupo de vendedores -- el
    // super_admin puede seguir ajustando esos campos manualmente despues,
    // esto solo rellena un punto de partida razonable.
    public static function recalcularPlanComercial(Get $get, Set $set): void
    {
        $plan = ($planId = $get('plan_id')) ? Plan::find($planId) : null;
        $paquete = ($paqueteId = $get('paquete_usuarios_id')) ? PaqueteUsuarios::find($paqueteId) : null;

        if ($plan) {
            $set('plan_meses', $plan->meses);
            $set('plan_ends_at', static::calculatePlanEndDate(
                $get('plan_started_at') ?: today()->toDateString(),
                (int) $plan->meses,
            ));
        }

        $set('max_vendedores', (int) ($plan?->usuarios_incluidos ?? 0) + (int) ($paquete?->usuarios_adicionales ?? 0));
    }

    public static function calcularTotalPlan(?int $planId, array $complementosIds, ?int $paqueteId): float
    {
        $plan = $planId ? Plan::find($planId) : null;
        $totalComplementos = (float) Complemento::whereIn('id', $complementosIds)->sum('precio');
        $paquete = $paqueteId ? PaqueteUsuarios::find($paqueteId) : null;

        return (float) ($plan?->precio ?? 0) + $totalComplementos + (float) ($paquete?->precio ?? 0);
    }

    public static function totalPlanHtml(Get $get): \Illuminate\Support\HtmlString
    {
        $total = static::calcularTotalPlan(
            $get('plan_id') ? (int) $get('plan_id') : null,
            $get('complementos_ids') ?? [],
            $get('paquete_usuarios_id') ? (int) $get('paquete_usuarios_id') : null,
        );

        return new \Illuminate\Support\HtmlString(
            '<span style="font-size:1.4rem;font-weight:700;">$ ' . number_format($total, 0, ',', '.') . '</span>'
        );
    }

    // Sincroniza la tabla pivote empresa_complementos, guardando el precio
    // vigente del complemento como "precio_aplicado" (snapshot) -- si mas
    // adelante el super_admin cambia el precio del complemento en el
    // catalogo, no altera retroactivamente lo ya aplicado a esta empresa.
    public static function sincronizarComplementos(User $empresa, array $complementosIds): void
    {
        $sync = Complemento::whereIn('id', $complementosIds)
            ->get()
            ->mapWithKeys(fn (Complemento $c) => [$c->id => ['precio_aplicado' => $c->precio]])
            ->all();

        $empresa->complementos()->sync($sync);
    }

    public static function saveFactusConfig(User $empresa, array $factus): void
    {
        $allowed = collect($factus)->only([
            'factus_enabled',
            'factus_environment',
            'factus_base_url',
            'factus_username',
            'factus_password',
            'factus_client_id',
            'factus_client_secret',
            'factus_numbering_range_id',
            'factus_credit_note_numbering_range_id',
            'factus_send_email',
            'nit',
            'prefijo',
            'rango_desde',
            'rango_hasta',
            'rango_actual',
            'numero_resolucion',
            'fecha_inicio',
            'fecha_fin',
            'llave',
            'expirado',
        ])->filter(fn ($value, string $key) => ! in_array($key, ['factus_password', 'factus_client_secret'], true) || filled($value))->all();

        foreach (['factus_numbering_range_id', 'factus_credit_note_numbering_range_id', 'rango_desde', 'rango_hasta', 'rango_actual'] as $integerKey) {
            if (array_key_exists($integerKey, $allowed) && blank($allowed[$integerKey])) {
                $allowed[$integerKey] = null;
            }
        }

        foreach (['fecha_inicio', 'fecha_fin'] as $dateKey) {
            if (array_key_exists($dateKey, $allowed) && blank($allowed[$dateKey])) {
                $allowed[$dateKey] = null;
            }
        }

        $empresa->configuracion()->updateOrCreate(
            ['empresa_id' => $empresa->id],
            array_merge([
                'nombre_empresa' => $empresa->name,
                'representante_legal' => $empresa->name,
                'nit' => $factus['nit'] ?? null,
                'telefono' => $empresa->telefono,
                'direccion' => $empresa->direccion,
                'activo' => true,
            ], $allowed),
        );
    }

    public static function factusFormState(User $empresa): array
    {
        $config = $empresa->configuracion;

        return [
            'factus_enabled' => (bool) ($config?->factus_enabled ?? false),
            'factus_environment' => $config?->factus_environment ?? 'sandbox',
            'factus_base_url' => $config?->factus_base_url,
            'factus_username' => $config?->factus_username,
            'factus_password' => null,
            'factus_client_id' => $config?->factus_client_id,
            'factus_client_secret' => null,
            'factus_numbering_range_id' => $config?->factus_numbering_range_id,
            'factus_credit_note_numbering_range_id' => $config?->factus_credit_note_numbering_range_id,
            'factus_send_email' => (bool) ($config?->factus_send_email ?? false),
            'nit' => $config?->nit,
            'prefijo' => $config?->prefijo,
            'rango_desde' => $config?->rango_desde,
            'rango_hasta' => $config?->rango_hasta,
            'rango_actual' => $config?->rango_actual,
            'numero_resolucion' => $config?->numero_resolucion,
            'fecha_inicio' => $config?->fecha_inicio?->toDateString(),
            'fecha_fin' => $config?->fecha_fin?->toDateString(),
        ];
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
