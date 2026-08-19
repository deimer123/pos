@component('mail::message')
# ¡Bienvenido, {{ $empresa->name }}!

Tu empresa ya fue creada y activada en {{ config('app.name') }}. Estos son los datos de acceso a tu panel:

@component('mail::panel')
**Usuario (correo):** {{ $empresa->email }}
**Contraseña:** {{ $password }}
@endcomponent

@component('mail::button', ['url' => $loginUrl])
Ingresar al panel
@endcomponent

Por seguridad, te recomendamos cambiar la contraseña después de tu primer ingreso.

@if ($plan)
## Plan contratado: {{ $plan->nombre }}

@if ($plan->descripcion)
{{ $plan->descripcion }}
@endif

- Duración: {{ $plan->meses }} {{ $plan->meses === 1 ? 'mes' : 'meses' }}
- Usuarios incluidos: {{ $plan->usuarios_incluidos }}
@endif

Si no esperabas este correo, por favor contacta al administrador del sistema.

Saludos,<br>
{{ config('app.name') }}
@endcomponent
