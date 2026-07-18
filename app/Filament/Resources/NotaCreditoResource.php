<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotaCreditoResource\Pages;
use App\Models\NotaCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotaCreditoResource extends Resource
{
    protected static ?string $model = NotaCredito::class;

    protected static ?string $navigationIcon  = 'heroicon-o-receipt-refund';
    protected static ?string $navigationLabel = 'Notas Crédito';
    protected static ?string $navigationGroup = '⚙️ Operaciones';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('empresa_id', $user?->getEmpresaActualId());
    }

    /* ==========================================================
     *  FORM (VIEW)
     * ========================================================== */
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Grid::make([
                'default' => 1,
                'md'      => 2,
            ])->schema([

                // -------- IDENTIFICACIÓN --------
                Forms\Components\Section::make('Identificación')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->disabled(),

                        // Usa el accessor factura_numero del modelo
                        Forms\Components\TextInput::make('factura_numero')
                            ->label('Factura asociada')
                            ->disabled(),
                    ])
                    ->icon('heroicon-o-tag')
                    ->collapsible()
                    ->columnSpan(1),

                // -------- PROVEEDOR --------
                Forms\Components\Section::make('Proveedor')
                    ->schema([
                        // Usa el accessor proveedor_nombre del modelo
                        Forms\Components\TextInput::make('proveedor_nombre')
                            ->label('Proveedor')
                            ->disabled(),
                    ])
                    ->icon('heroicon-o-building-storefront')
                    ->collapsible()
                    ->columnSpan(1),

                // -------- VALORES --------
                Forms\Components\Section::make('Valores')
                    ->schema([
                        Forms\Components\TextInput::make('total')
                            ->label('Total devuelto')
                            ->disabled()
                            ->formatStateUsing(function ($state) {
                                if (! $state) {
                                    return '$ 0';
                                }
                                return '$ ' . number_format((float) $state, 0, ',', '.');
                            }),
                    ])
                    ->icon('heroicon-o-banknotes')
                    ->collapsible()
                    ->columnSpan(1),

                // -------- FECHAS --------
                Forms\Components\Section::make('Fechas')
                    ->schema([
                        Forms\Components\TextInput::make('fecha_cobro')
                            ->label('Fecha cobro')
                            ->disabled(),

                        Forms\Components\TextInput::make('created_at')
                            ->label('Fecha creación')
                            ->disabled(),
                    ])
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible()
                    ->columnSpan(1),
            ])
            ->columnSpanFull()
            ->maxWidth('5xl'),

            // -------- MOTIVO --------
            Forms\Components\Section::make('Motivo')
                ->schema([
                    Forms\Components\Textarea::make('motivo')
                        ->label('Motivo')
                        ->disabled()
                        ->rows(3),
                ])
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }

    /* ==========================================================
     *  TABLA
     * ========================================================== */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                if (! $user->hasRole('super_admin')) {
                    $empresaId = $user->empresa_id ?: $user->id;
                    $query->where('empresa_id', $empresaId);
                }
                // ❌ OJO: YA NO FILTRAMOS POR ESTADO AQUÍ
            })

            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('NC')
                    ->sortable()
                    ->searchable(),

                // Usa el accessor factura_numero
                Tables\Columns\TextColumn::make('factura_numero')
                    ->label('Factura')
                    ->sortable(),

                // Usa empresa_deudora directamente (nombre congelado)
                Tables\Columns\TextColumn::make('empresa_deudora')
                    ->label('Proveedor')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'cobrada',
                        'gray'    => 'anulada',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) =>
                        number_format($state, 0, ',', '.')
                    )
                    ->sortable(),
            ])

            ->filters([

                // -------- FILTRO POR PROVEEDOR (empresa_deudora) --------
                Tables\Filters\SelectFilter::make('empresa_deudora')
                    ->label('Proveedor')
                    ->options(
                        NotaCredito::query()
                            ->where('empresa_id', auth()->user()->getEmpresaActualId())
                            ->whereNotNull('empresa_deudora')
                            ->orderBy('empresa_deudora')
                            ->pluck('empresa_deudora', 'empresa_deudora')
                            ->toArray()
                    )
                    ->placeholder('Todos')   // texto arriba
                    ->searchable(),

                // -------- FILTRO POR ESTADO --------
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'cobrada'   => 'Cobrada',
                        'anulada'   => 'Anulada',
                    ])
                    ->placeholder('Todos')
                    ->default('pendiente'),   // 👈 por defecto solo pendientes
            ])

            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (NotaCredito $record) =>
                        route('notas-credito.pdf', ['nota' => $record->id])
                    )
                    ->openUrlInNewTab(),
            ])

            ->bulkActions([]);
    }

    /* ==========================================================
     *  PAGES
     * ========================================================== */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotaCreditos::route('/'),
            'view'  => Pages\ViewNotaCredito::route('/{record}'),
        ];
    }

       // Solo ADMIN_EMPRESA puede acceder
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) $user && ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('notas_credito')));
    }

    public static function canViewAny(): bool
{
    return static::canAccess();
}

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
