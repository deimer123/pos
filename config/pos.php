<?php

return [

    // online (droplet, MySQL) | hibrida (Turion, sincroniza) | local (standalone, nunca sincroniza).
    // Turion/Local lo inyectan como env var al arrancar (ver
    // src-tauri/src/lib.rs::preparar_entorno_local()); el droplet no la
    // setea, así que el default 'online' aplica ahí.
    'edition' => env('POS_EDITION', 'online'),

    // Fingerprint estable de esta máquina (solo aplica a la edición Local,
    // inyectado por Rust igual que POS_EDITION). Ver App\Services\LocalLicense.
    'machine_id' => env('POS_MACHINE_ID'),

    // Version del bundle actual (misma env var que ya inyecta Rust para
    // TurionSyncBar) -- se guarda en el manifest.json de cada respaldo
    // (ver App\Filament\Pages\RespaldoLocal) para poder diagnosticar con
    // que version se genero.
    'version' => env('TURION_VERSION', 'dev'),

    'license' => [
        'format_version' => 1,

        // Llave pública Ed25519 (base64), usada para VERIFICAR códigos de
        // activación en el cliente (Turion/droplet no la necesitan). Es
        // pública -- se puede commitear como default una vez generada con
        // "php artisan pos:licencia:generar-llaves".
        'public_key' => env('POS_LOCAL_LICENSE_PUBLIC_KEY', 'Z7FFs+NLH1nyHXFJVZrjBT8d0+1Sc160+QMkjtixvX4='),

        // Llave privada Ed25519 (base64), usada para FIRMAR códigos de
        // activación. Solo debe existir como secret en el droplet -- nunca
        // en una instalación cliente (Turion o Local).
        'private_key' => env('POS_LOCAL_LICENSE_PRIVATE_KEY'),
    ],

];
