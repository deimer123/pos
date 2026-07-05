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
        if (! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) {
            return false;
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        return (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_hotel');
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

                    Forms\Components\Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),

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
                        ->label('❄️ Tiene aire acondicionado'),

                    Forms\Components\Toggle::make('tiene_ventilador')
                        ->label('🌀 Tiene ventilador'),
                ]),
            ]),

            Forms\Components\Section::make('Precio por número de personas')
                ->description('El precio es por noche completa según cuántas personas se hospeden (no tiene que ser proporcional). Una habitación con aire/ventilador normalmente se cobra más que una sin.')
                ->schema(
                    collect(range(1, self::MAX_PERSONAS))->map(
                        fn ($n) => Forms\Components\TextInput::make("precio_{$n}")
                            ->label($n == 1 ? '1 persona' : "{$n} personas")
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
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
