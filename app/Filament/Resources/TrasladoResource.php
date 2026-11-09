<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrasladoResource\Pages;
use App\Models\Product;
use App\Models\Traslado;
use App\Models\User;
use App\Services\Grupo\ConsultaInventarioGrupo;
use App\Services\Grupo\TrasladoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Traslados de mercancia entre sucursales (empresas del mismo grupo). Solo el
// administrador de una empresa que pertenece a un grupo. No hay edicion ni
// borrado: un traslado se envia, y luego se recibe, rechaza o cancela.
class TrasladoResource extends Resource
{
    protected static ?string $model = Traslado::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = '📦 Inventario';
    protected static ?string $navigationLabel = 'Traslados';
    protected static ?string $modelLabel = 'Traslado';
    protected static ?string $pluralModelLabel = 'Traslados';
    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->hasRole('admin_empresa')
            && app(ConsultaInventarioGrupo::class)->disponible($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // Traslados por recibir de mi empresa.
    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $pendientes = Traslado::where('destino_id', auth()->user()->getEmpresaActualId())
            ->where('estado', Traslado::ENVIADO)
            ->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return parent::getEloquentQuery()
            ->where(fn ($q) => $q->where('origen_id', $empresaId)->orWhere('destino_id', $empresaId));
    }

    public static function form(Form $form): Form
    {
        $yo = auth()->user();
        $miEmpresa = $yo->empresaPrincipal();
        $servicio = app(TrasladoService::class);

        return $form->schema([
            Forms\Components\Section::make('Traslado')
                ->description('La mercancía sale de tu inventario al enviar y entra al de la otra sucursal cuando la confirme.')
                ->schema([
                    Forms\Components\Select::make('destino_id')
                        ->label('Enviar a')
                        ->required()
                        ->native(false)
                        ->options(function () use ($yo, $miEmpresa, $servicio) {
                            $ids = app(ConsultaInventarioGrupo::class)->idsDelGrupo($yo);

                            return User::whereIn('id', $ids)
                                ->where('id', '!=', $miEmpresa->id)
                                ->orderBy('name')
                                ->get()
                                ->filter(fn (User $u) => $servicio->motivoNoPuede($miEmpresa, $u) === null)
                                ->pluck('name', 'id')
                                ->all();
                        }),

                    Forms\Components\Textarea::make('observacion')
                        ->label('Observación')
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make('Productos')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->addActionLabel('Agregar producto')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\Select::make('id_producto')
                                ->label('Producto')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search) => static::productosEnviables($miEmpresa->id)
                                    ->where('descripcion_larga', 'like', "%{$search}%")
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (Product $p) => [$p->id_producto => "{$p->descripcion_larga} (stock {$p->existencias})"])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value) => static::productosEnviables($miEmpresa->id)
                                    ->where('id_producto', $value)->value('descripcion_larga')),

                            Forms\Components\TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->minValue(0.01),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    // Productos de la empresa que se pueden trasladar (con inventario y stock).
    public static function productosEnviables(int $empresaId)
    {
        return Product::query()
            ->where('empresa_id', $empresaId)
            ->where('maneja_inventario', true)
            ->where('tipo_producto', 'producto')
            ->where('existencias', '>', 0);
    }

    public static function table(Table $table): Table
    {
        $miEmpresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('enviado_at')->label('Enviado')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('')
                    ->badge()
                    ->state(fn (Traslado $t) => (int) $t->origen_id === (int) $miEmpresaId ? 'Enviado' : 'Recibido')
                    ->color(fn (string $state) => $state === 'Enviado' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('origen.name')->label('De'),
                Tables\Columns\TextColumn::make('destino.name')->label('Para'),
                Tables\Columns\TextColumn::make('items_count')->label('Productos')->counts('items'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        Traslado::ENVIADO => 'warning',
                        Traslado::RECIBIDO => 'success',
                        default => 'danger',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')->options([
                    Traslado::ENVIADO => 'Enviado',
                    Traslado::RECIBIDO => 'Recibido',
                    Traslado::RECHAZADO => 'Rechazado',
                    Traslado::CANCELADO => 'Cancelado',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->infolist(fn (Infolist $infolist) => static::infolist($infolist)),

                static::accion('recibir', 'Recibir', 'heroicon-o-check-circle', 'success', 'destino_id', $miEmpresaId,
                    'Se sumará la mercancía a tu inventario. Si algún producto no existe en tu empresa, se creará.'),
                static::accion('rechazar', 'Rechazar', 'heroicon-o-x-circle', 'danger', 'destino_id', $miEmpresaId,
                    'La mercancía volverá al inventario de la sucursal que la envió.'),
                static::accion('cancelar', 'Cancelar', 'heroicon-o-arrow-uturn-left', 'gray', 'origen_id', $miEmpresaId,
                    'La mercancía volverá a tu inventario.'),
            ]);
    }

    // Accion de tabla que llama al servicio y muestra el resultado o el motivo.
    private static function accion(string $metodo, string $etiqueta, string $icono, string $color, string $columna, int $miEmpresaId, string $descripcion): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($metodo)
            ->label($etiqueta)
            ->icon($icono)
            ->color($color)
            ->visible(fn (Traslado $t) => $t->estaEnviado() && (int) $t->{$columna} === $miEmpresaId)
            ->requiresConfirmation()
            ->modalHeading("{$etiqueta} traslado")
            ->modalDescription($descripcion)
            ->action(function (Traslado $t) use ($metodo, $etiqueta) {
                try {
                    app(TrasladoService::class)->{$metodo}(auth()->user(), $t);

                    Notification::make()->title("Traslado #{$t->id}: {$etiqueta} listo")->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('origen.name')->label('De'),
                Infolists\Components\TextEntry::make('destino.name')->label('Para'),
                Infolists\Components\TextEntry::make('estado')->badge()->formatStateUsing(fn (string $state) => ucfirst($state)),
                Infolists\Components\TextEntry::make('enviado_at')->label('Enviado')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('resuelto_at')->label('Resuelto')->dateTime('d/m/Y H:i')->placeholder('—'),
                Infolists\Components\TextEntry::make('observacion')->label('Observación')->placeholder('—')->columnSpanFull(),
            ])->columns(3),

            Infolists\Components\RepeatableEntry::make('items')
                ->label('Productos')
                ->schema([
                    Infolists\Components\TextEntry::make('nombre')->label('Producto'),
                    Infolists\Components\TextEntry::make('cantidad')->label('Cantidad'),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTraslados::route('/'),
            'create' => Pages\CreateTraslado::route('/create'),
        ];
    }
}
