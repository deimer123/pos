<?php

namespace App\Filament\Resources\CompraResource\RelationManagers;

use App\Models\Pago;
use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';
    protected static ?string $recordTitleAttribute = 'fecha';
    protected static ?string $title = 'Pagos de la compra';

    // 👇 Asegura que se muestre si la compra es crédito confirmada
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
{
    return ($ownerRecord->tipo_pago ?? '') === 'credito';
}

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha de pago')
                ->required()
                ->default(now())
                ->native(false)
                ->displayFormat('d/m/Y'),

            Forms\Components\TextInput::make('monto')
                ->label('Monto')
                ->numeric()
                ->prefix('$')
                ->required()
                ->minValue(0.01),

            Forms\Components\Select::make('metodo_pago')
                ->label('Método de pago')
                ->options([
                    'efectivo' => 'Efectivo',
                    'transferencia' => 'Transferencia',
                    'tarjeta' => 'Tarjeta',
                    'otro' => 'Otro',
                ])
                ->required(),

            Forms\Components\TextInput::make('referencia')
                ->label('Referencia')
                ->maxLength(150)
                ->placeholder('Número de comprobante'),

            Forms\Components\Textarea::make('observaciones')
                ->label('Observaciones')
                ->rows(2)
                ->placeholder('Notas del pago...'),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('monto')
                    ->label('Monto')    
                     ->formatStateUsing(fn ($state) => '$ ' . number_format((float)($state ?? 0), 0, ',', '.'))                
                    ->sortable(),

                Tables\Columns\TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->badge(),

                Tables\Columns\TextColumn::make('referencia')
                    ->label('Referencia')
                    ->limit(20),

                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(30),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar pago')
                    ->after(function (Pago $record) {
                        $compra = $record->compra;
                        if ($compra) {
                            $pagado = $compra->pagos()->sum('monto');
                            $saldo = max(0, $compra->total - $pagado);
                            $compra->update(['saldo' => $saldo]);
                        }

                        Notification::make()
                            ->title('Pago registrado correctamente.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (Pago $record) {
                        $compra = $record->compra;
                        if ($compra) {
                            $pagado = $compra->pagos()->sum('monto');
                            $saldo = max(0, $compra->total - $pagado);
                            $compra->update(['saldo' => $saldo]);
                        }

                        Notification::make()
                            ->title('Pago eliminado y saldo actualizado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No hay pagos registrados.')
            ->emptyStateDescription('Registra un pago para esta compra.')
            ->defaultSort('fecha', 'desc');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        $data['empresa_id'] = $user->empresa_id ?? $user->id;
        $data['user_id'] = $user->id;
        return $data;
    }
}
