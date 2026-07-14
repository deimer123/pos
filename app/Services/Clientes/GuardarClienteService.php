<?php

namespace App\Services\Clientes;

use App\Models\Actor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Logica de "crear cliente nuevo" extraida de
 * App\Livewire\CarritoVenta::guardarCliente() (extraccion pura, sin
 * cambiar el comportamiento online), para reutilizarla desde la
 * sincronizacion de clientes creados offline.
 */
class GuardarClienteService
{
    /**
     * @param  array<string, mixed>  $datos  tipo_documento_id, identificacion, nombre,
     *   razon_social, telefono, email, direccion, departamento_id, ciudad_id,
     *   tipo_persona, regimen_tributario, responsable_iva
     *
     * @throws ValidationException
     */
    public function crear(int $empresaId, array $datos): Actor
    {
        $responsableIva = (int) ($datos['responsable_iva'] ?? 0);
        $regimenTributario = $datos['regimen_tributario'] ?? null;
        $tipoPersona = $datos['tipo_persona'] ?? null;
        $tipoDocumentoId = $datos['tipo_documento_id'] ?? null;

        if ($regimenTributario === 'simplificado' && $responsableIva === 1) {
            throw ValidationException::withMessages([
                'responsable_iva' => 'Un régimen simplificado no puede ser responsable de IVA.',
            ]);
        }

        if ($regimenTributario === 'comun' && $responsableIva !== 1) {
            throw ValidationException::withMessages([
                'responsable_iva' => 'Un régimen común debe ser responsable de IVA.',
            ]);
        }

        if ($tipoPersona === 'juridica' && (int) $tipoDocumentoId !== 6) {
            throw ValidationException::withMessages([
                'tipo_documento_id' => 'Una persona jurídica solo puede tener NIT como tipo de documento.',
            ]);
        }

        $validador = Validator::make($datos, [
            'identificacion' => [
                'required',
                Rule::unique('actors', 'identificacion')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'nombre' => [
                'required',
                Rule::unique('actors', 'nombre')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('actors', 'email')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'telefono' => 'required|numeric',
            'departamento_id' => 'required|exists:departamentos,id',
            'ciudad_id' => 'required|exists:ciudades,id',
            'direccion' => 'required',
            'responsable_iva' => 'required|in:0,1',
            'regimen_tributario' => 'required',
            'tipo_persona' => 'required',
        ], [
            'identificacion.required' => 'Debe ingresar una identificacion.',
            'identificacion.unique' => 'Este numero de identificacion ya esta registrado.',
            'nombre.required' => 'Debe ingresar un nombre.',
            'nombre.unique' => 'El nombre ya existe.',
            'email.required' => 'Debe ingresar un correo electronico.',
            'email.email' => 'Debe ingresar un correo valido.',
            'email.unique' => 'Este correo ya esta registrado.',
            'telefono.required' => 'Debe ingresar un telefono.',
            'departamento_id.required' => 'Seleccione un departamento.',
            'ciudad_id.required' => 'Seleccione una ciudad.',
        ]);

        $validador->validate();

        return Actor::create([
            'id_clip_pro' => Actor::max('id_clip_pro') + 1,
            'empresa_id' => $empresaId,
            'tipo_documento_id' => $tipoDocumentoId,
            'identificacion' => $datos['identificacion'],
            'nombre' => $datos['nombre'],
            'razon_social' => $datos['razon_social'] ?? null,
            'telefono' => $datos['telefono'],
            'email' => $datos['email'],
            'departamento_id' => $datos['departamento_id'],
            'ciudad_id' => $datos['ciudad_id'],
            'direccion' => $datos['direccion'],
            'tipo_persona' => $tipoPersona,
            'regimen_tributario' => $regimenTributario,
            'responsable_iva' => $responsableIva,
            'clasificacion' => 'cliente',
            'tipo' => 1,
        ]);
    }

    /**
     * Version reducida para crear un cliente rapido desde el POS sin
     * conexion: solo nombre + identificacion son obligatorios (los demas
     * campos que exige crear() -- email, departamento, ciudad, regimen
     * tributario -- son NULL en la base de datos hoy, asi que no bloquean
     * nada; solo hacen falta para el formulario COMPLETO de la
     * administracion). Este cliente rapido se puede completar despues
     * desde el panel si hace falta para facturacion electronica/credito.
     *
     * @param  array<string, mixed>  $datos  nombre, identificacion, telefono?, direccion?
     *
     * @throws ValidationException
     */
    public function crearRapido(int $empresaId, array $datos): Actor
    {
        $validador = Validator::make($datos, [
            'nombre' => [
                'required',
                Rule::unique('actors', 'nombre')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'identificacion' => [
                'required',
                Rule::unique('actors', 'identificacion')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
        ], [
            'nombre.required' => 'Debe ingresar un nombre.',
            'nombre.unique' => 'El nombre ya existe.',
            'identificacion.required' => 'Debe ingresar una identificacion.',
            'identificacion.unique' => 'Este numero de identificacion ya esta registrado.',
        ]);

        $validador->validate();

        return Actor::create([
            'id_clip_pro' => Actor::max('id_clip_pro') + 1,
            'empresa_id' => $empresaId,
            'tipo_documento_id' => 3,
            'identificacion' => $datos['identificacion'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'clasificacion' => 'cliente',
            'tipo' => 1,
        ]);
    }
}
