<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductComboResource\Pages;
use App\Models\Product;
use App\Models\ProductCombo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductComboResource extends Resource
{
    protected static ?string $model = ProductCombo::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = '📦 Inventario';
    protected static ?string $navigationLabel = 'Combos';
    protected static ?string $modelLabel = 'Combo';
    protected static ?string $pluralModelLabel = 'Combos';
    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('admin_empresa') || $user->hasRole('digitador'));
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
            Forms\Components\Group::make()
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([

            Forms\Components\Grid::make(2)->extraAttributes(['class' => 'producto-linea-1'])->schema([
            Forms\Components\Select::make('product_id')
                ->label('Producto')
                ->options(
                    Product::where('empresa_id', $empresaId)
                        ->orderBy('descripcion_larga')
                        ->pluck('descripcion_larga', 'id')
                )
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    $set('precio_unitario_combo', null);
                }),

            Forms\Components\TextInput::make('nombre')
                ->label('Nombre del combo (opcional)')
                ->placeholder('Ej: Promo 3x2, Happy Hour...')
                ->maxLength(100),
            ]),

            Forms\Components\Grid::make(2)->extraAttributes(['class' => 'producto-linea-2'])->schema([
            Forms\Components\TextInput::make('cantidad_minima')
                ->label('Cantidad mínima para activar')
                ->numeric()
                ->minValue(2)
                ->step(1)
                ->required()
                ->helperText('Desde esta cantidad se aplica el precio especial'),

            Forms\Components\TextInput::make('precio_unitario_combo')
                ->label('Precio unitario del combo')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->required()
                ->live(debounce: 400)
                ->helperText('Precio por unidad cuando se activa el combo'),

            // Info card: precios y utilidades
            Forms\Components\Placeholder::make('info_precios')
                ->label('')
                ->columnSpanFull()
                ->content(function (Get $get) {
                    $productId = $get('product_id');
                    $precioCombo = (float) $get('precio_unitario_combo');

                    if (! $productId) {
                        return new \Illuminate\Support\HtmlString('');
                    }

                    $product = Product::find($productId);
                    if (! $product) {
                        return new \Illuminate\Support\HtmlString('');
                    }

                    // Calcular igual que el resource de productos: precio_con_descuento * (1 + iva_venta%)
                    $precioConDesc = (float) ($product->precio_con_descuento ?: $product->precio_costo);
                    $ivaVenta      = (float) $product->iva_venta;
                    $costoIvaCalc  = (float) $product->costo_iva;
                    $costoTotal    = $costoIvaCalc > 0 ? $costoIvaCalc : round($precioConDesc * (1 + $ivaVenta / 100), 2);
                    $precioActual = (float) $product->precio_venta1;
                    $utilActualAbs = $precioActual - $costoTotal;
                    $utilActualPct = $precioActual > 0 ? ($utilActualAbs / $precioActual) * 100 : 0;

                    $comboHtml = '';
                    if ($precioCombo > 0) {
                        $utilComboAbs = $precioCombo - $costoTotal;
                        $utilComboPct = $precioCombo > 0 ? ($utilComboAbs / $precioCombo) * 100 : 0;
                        $colorUtil    = $utilComboAbs >= 0 ? '#16a34a' : '#dc2626';
                        $colorBadge   = $utilComboAbs >= 0 ? '#dcfce7' : '#fee2e2';
                        $arrowIcon    = $precioCombo < $precioActual ? '↓' : ($precioCombo > $precioActual ? '↑' : '→');
                        $arrowColor   = $precioCombo < $precioActual ? '#dc2626' : '#16a34a';

                        $comboHtml = "
                        <div style='border-left:4px solid #7c3aed; padding-left:12px; margin-top:10px;'>
                            <div style='font-size:11px; font-weight:600; color:#7c3aed; margin-bottom:6px;'>🎁 CON PRECIO COMBO</div>
                            <div style='display:flex; gap:24px; flex-wrap:wrap;'>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Precio combo</div>
                                    <div style='font-size:16px; font-weight:700; color:#7c3aed;'>\$" . number_format($precioCombo, 0, ',', '.') . "
                                        <span style='font-size:13px; color:{$arrowColor};'>{$arrowIcon}</span>
                                    </div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Utilidad unitaria</div>
                                    <div style='font-size:15px; font-weight:700; color:{$colorUtil};'>\$" . number_format($utilComboAbs, 0, ',', '.') . "</div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Utilidad %</div>
                                    <div style='font-size:15px; font-weight:700;'>
                                        <span style='background:{$colorBadge}; color:{$colorUtil}; padding:2px 8px; border-radius:6px;'>" . number_format($utilComboPct, 1) . "%</span>
                                    </div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Descuento vs precio normal</div>
                                    <div style='font-size:15px; font-weight:700; color:#dc2626;'>-\$" . number_format($precioActual - $precioCombo, 0, ',', '.') . "</div>
                                </div>
                            </div>
                        </div>";
                    }

                    $html = "
                    <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-bottom:4px;'>
                        <div style='border-left:4px solid #3b82f6; padding-left:12px; margin-bottom:10px;'>
                            <div style='font-size:11px; font-weight:600; color:#3b82f6; margin-bottom:6px;'>📊 PRECIO ACTUAL DEL PRODUCTO</div>
                            <div style='display:flex; gap:24px; flex-wrap:wrap;'>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Precio de venta</div>
                                    <div style='font-size:16px; font-weight:700; color:#1e293b;'>\$" . number_format($precioActual, 0, ',', '.') . "</div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Costo + IVA venta</div>
                                    <div style='font-size:16px; font-weight:700; color:#64748b;'>\$" . number_format($costoTotal, 0, ',', '.') . "</div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Utilidad unitaria</div>
                                    <div style='font-size:15px; font-weight:700; color:#16a34a;'>\$" . number_format($utilActualAbs, 0, ',', '.') . "</div>
                                </div>
                                <div>
                                    <div style='font-size:10px; color:#6b7280;'>Utilidad %</div>
                                    <div style='font-size:15px; font-weight:700;'>
                                        <span style='background:#dcfce7; color:#16a34a; padding:2px 8px; border-radius:6px;'>" . number_format($utilActualPct, 1) . "%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {$comboHtml}
                    </div>";

                    return new \Illuminate\Support\HtmlString($html);
                }),
            ]),

            Forms\Components\Grid::make(1)->extraAttributes(['class' => 'producto-linea-1'])->schema([
            Forms\Components\Toggle::make('activo')
                ->label('Activo')
                ->default(true),
            ]),

            ]),

            Forms\Components\Hidden::make('empresa_id')
                ->default($empresaId),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre combo')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('cantidad_minima')
                    ->label('Cant. mínima')
                    ->suffix(' und')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('precio_unitario_combo')
                    ->label('Precio combo')
                    ->money('COP')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Borrar'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProductCombos::route('/'),
            'create' => Pages\CreateProductCombo::route('/create'),
            'edit'   => Pages\EditProductCombo::route('/{record}/edit'),
        ];
    }
}
