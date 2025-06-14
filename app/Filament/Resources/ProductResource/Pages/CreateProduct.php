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

   
}