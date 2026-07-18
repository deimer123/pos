<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HotelHabitacionResource\Pages;
use App\Models\HotelHabitacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HotelHabitacionResource extends Resource
{
    protected static ?string $model = HotelHabitacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Habitaciones';

    protected static ?string $modelLabel = 'Habitación';

    protected static ?string $pluralModelLabel = 'Habitaciones';

    protected static ?string $navigationGroup = '🏨 Hotel';

    protected static ?int $navigationSort = 10;

    public const MAX_PERSONAS = 6;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $tieneRol = $user && ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('habitaciones')));

        if (! $tieneRol) {
            return false;
        }

        $empresaId = $user->getEmpresaActualId();

        return (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_hotel');
    }

    private static function moneyField(string $name, string $label): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->prefix('$')
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, callable $set) use ($name) {
                $digits = preg_replace('/\D/', '', (string) $state);
                $set($name, $digits === '' ? null : number_format((int) $digits, 0, ',', '.'));
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos de la habitación')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('numero')
                        ->label('Número de habitación')
                        ->required()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('zona')
                        ->label('Zona / Piso')
                        ->placeholder('Ej: Piso 1, Zona A...')
                        ->maxLength(60),

                    Forms\Components\TextInput::make('camas_dobles')
                        ->label('Camas dobles')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('camas_sencillas')
                        ->label('Camas sencillas')
                        ->numeric()
                        ->minValue(0)
                        ->default(1)
                        ->required(),

                    Forms\Components\Toggle::make('tiene_aire')
                        ->label('❄️ Tiene aire acondicionado')
                        ->live(),

                    Forms\Components\Toggle::make('tiene_ventilador')
                        ->label('🌀 Tiene ventilador')
                        ->live(),

                    static::moneyField('recargo_aire', 'Recargo por aire (por noche)')
                        ->visible(fn (Forms\Get $get) => (bool) $get('tiene_aire')),

                    static::moneyField('recargo_ventilador', 'Recargo por ventilador (por noche)')
                        ->visible(fn (Forms\Get $get) => (bool) $get('tiene_ventilador')),

                    Forms\Components\Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),
                ]),
            ]),

            Forms\Components\Section::make('Precio por número de personas')
                ->description('El precio base es por noche completa según cuántas personas se hospeden (no tiene que ser proporcional). El recargo de aire/ventilador (si aplica) se suma aparte.')
                ->schema(
                    collect(range(1, self::MAX_PERSONAS))->map(
                        fn ($n) => static::moneyField("precio_{$n}", $n == 1 ? '1 persona' : "{$n} personas")
                    )->toArray()
                )
                ->columns(3),

            Forms\Components\Section::make('Observaciones')->schema([
                Forms\Components\Textarea::make('observaciones')
                    ->label('Observaciones')
                    ->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(HotelHabitacion::query()->where('empresa_id', $empresaId))
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('zona')
                    ->label('Zona/Piso')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('camas_dobles')
                    ->label('Camas dobles')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('camas_sencillas')
                    ->label('Camas sencillas')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('capacidad_maxima')
                    ->label('Capacidad')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('tiene_aire')
                    ->label('Aire')
                    ->boolean(),

                Tables\Columns\IconColumn::make('tiene_ventilador')
                    ->label('Ventilador')
                    ->boolean(),

                Tables\Columns\TextColumn::make('precio_desde')
                    ->label('Desde (1 pers.)')
                    ->formatStateUsing($money),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona/Piso')
                    ->options(fn () => HotelHabitacion::where('empresa_id', $empresaId)
                        ->whereNotNull('zona')
                        ->where('zona', '!=', '')
                        ->distinct()
                        ->pluck('zona', 'zona')
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHotelHabitaciones::route('/'),
            'create' => Pages\CreateHotelHabitacion::route('/create'),
            'edit'   => Pages\EditHotelHabitacion::route('/{record}/edit'),
        ];
    }
}
