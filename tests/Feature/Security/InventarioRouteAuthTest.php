<?php

use App\Models\User;

// Regresion de la vulnerabilidad corregida: /inventario estaba registrada
// dos veces en routes/web.php, una sin ningun middleware (ganaba por orden
// de registro) y otra dentro de un grupo 'auth' que nunca se ejecutaba.

test('un visitante sin sesion no puede ver /inventario', function () {
    $response = $this->get('/inventario');

    $response->assertStatus(302);
});

test('un usuario autenticado si puede ver /inventario', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    $response = $this->actingAs($empresa)->get('/inventario');

    $response->assertOk();
});
