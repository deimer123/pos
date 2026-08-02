<?php

namespace App\Services\LocalLicense;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use DateTimeImmutable;

/**
 * Contenido (payload) de un código de activación de la edición Local, ya
 * firmado o ya verificado -- ver FirmadorLicencia (droplet) y
 * ValidadorLicencia (cliente).
 */
final class CodigoActivacion
{
    public function __construct(
        public readonly string $licenciaId,
        public readonly int $empresaId,
        public readonly string $empresaNombre,
        public readonly string $machineId,
        public readonly DateTimeImmutable $issuedAt,
        public readonly int $formatVersion = 1,
        // 'servidor' (corre PHP+SQLite, como toda instalacion Local hasta
        // ahora) o 'cliente' (terminal sin base propia, ver
        // EmparejarTerminalLocalController). Opcional A PROPOSITO: los
        // codigos ya emitidos antes de este campo no lo traen, y deben
        // seguir siendo validos como 'servidor' (ver fromPayloadArray()).
        public readonly string $rol = 'servidor',
    ) {
    }

    /**
     * El orden de las claves acá define exactamente los bytes que se
     * firman (ver FirmadorLicencia::firmar()) -- no reordenar sin razón,
     * aunque en la práctica la firma nunca se re-verifica contra un JSON
     * re-serializado desde este array (ValidadorLicencia verifica sobre
     * los bytes originales decodificados, no sobre esto).
     */
    public function toPayloadArray(): array
    {
        return [
            'v' => $this->formatVersion,
            'licencia_id' => $this->licenciaId,
            'empresa_id' => $this->empresaId,
            'empresa_nombre' => $this->empresaNombre,
            'machine_id' => $this->machineId,
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'rol' => $this->rol,
        ];
    }

    public static function fromPayloadArray(array $payload): self
    {
        foreach (['v', 'licencia_id', 'empresa_id', 'empresa_nombre', 'machine_id', 'issued_at'] as $campo) {
            if (! array_key_exists($campo, $payload)) {
                throw new LicenciaInvalidaException("El codigo de activacion no trae el campo requerido '{$campo}'.");
            }
        }

        try {
            $issuedAt = new DateTimeImmutable((string) $payload['issued_at']);
        } catch (\Exception) {
            throw new LicenciaInvalidaException('El codigo de activacion trae una fecha de emision invalida.');
        }

        return new self(
            licenciaId: (string) $payload['licencia_id'],
            empresaId: (int) $payload['empresa_id'],
            empresaNombre: (string) $payload['empresa_nombre'],
            machineId: (string) $payload['machine_id'],
            issuedAt: $issuedAt,
            formatVersion: (int) $payload['v'],
            rol: (string) ($payload['rol'] ?? 'servidor'),
        );
    }
}
