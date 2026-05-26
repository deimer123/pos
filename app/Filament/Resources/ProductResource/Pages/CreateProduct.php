<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use App\Models\AlternateCode;
use App\Models\Product;


class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
{
    /** @var Product $product */
    $product = $this->record;

    $empresaId = auth()->user()->getEmpresaActualId();

    // 🔒 Evitar duplicado
    $existe = AlternateCode::where('empresa_id', $empresaId)
        ->where('product_id', $product->id)
        ->where('code', (string) $product->id_producto)
        ->exists();

    if (! $existe) {
        AlternateCode::create([
            'empresa_id' => $empresaId,
            'product_id' => $product->id,
            'code'       => (string) $product->id_producto,
        ]);
    }
}

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}