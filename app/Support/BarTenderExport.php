<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class BarTenderExport
{
    /**
     * Genera el .bat que crea el CSV de datos y abre BarTender con la
     * plantilla correspondiente para imprimir las etiquetas.
     *
     * @param  array<int, array{product_id: string, nombre_producto: string, cantidad: float|int, precio_venta: float|int}>  $lineas
     */
    public static function generarBat(array $lineas, string $tamano, string $precio, string $nombreArchivo): StreamedResponse
    {
        $csv = fopen('php://temp', 'r+');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['product_id', 'nombre_producto', 'cantidad', 'precio_venta']);

        foreach ($lineas as $linea) {
            $cantidad = max(1, (int) ceil((float) $linea['cantidad']));

            for ($i = 0; $i < $cantidad; $i++) {
                fputcsv($csv, [
                    (string) $linea['product_id'],
                    (string) $linea['nombre_producto'],
                    '1',
                    (string) (int) round((float) $linea['precio_venta']),
                ]);
            }
        }

        rewind($csv);
        $csvBase64 = base64_encode(stream_get_contents($csv));
        fclose($csv);

        $plantilla = match ($tamano . '_' . $precio) {
            '2x50x25_sin_precio' => 'C:\POS\BarTender\etiquetas_2x50x25_sin_precio.btw',
            '3x32x25_con_precio' => 'C:\POS\BarTender\etiquetas_3x32x25_con_precio.btw',
            '3x32x25_sin_precio' => 'C:\POS\BarTender\etiquetas_3x32x25_sin_precio.btw',
            default => 'C:\POS\BarTender\etiquetas_2x50x25_con_precio.btw',
        };

        // El CSV en base64 se escribe en trozos pequenos (uno por linea)
        // porque cmd.exe tiene un limite de ~8191 caracteres por linea de
        // comando: si se manda todo el base64 de una sola vez como
        // argumento a PowerShell, con compras grandes se corta la linea y
        // el CSV nunca se llega a crear (error #6241 de BarTender).
        $lineasBat = [
            '@echo off',
            'setlocal',
            'title POS - Imprimir etiquetas BarTender',
            '',
            'set "BTW_FILE=' . $plantilla . '"',
            'set "CSV_FILE=%TEMP%\\bartender_' . $nombreArchivo . '.csv"',
            'set "B64_FILE=%TEMP%\\bartender_' . $nombreArchivo . '.b64"',
            'set "BARTENDER_EXE="',
            '',
            'for %%P in ("C:\\Program Files\\Seagull\\BarTender 2022\\bartend.exe" "C:\\Program Files\\Seagull\\BarTender 2021\\bartend.exe" "C:\\Program Files\\Seagull\\BarTender 2020\\bartend.exe" "C:\\Program Files (x86)\\Seagull\\BarTender Suite\\bartend.exe") do if exist %%~P set "BARTENDER_EXE=%%~P"',
            '',
            'if not exist "%BTW_FILE%" (',
            '  echo No se encontro la plantilla:',
            '  echo %BTW_FILE%',
            '  echo.',
            '  echo Copia tu archivo .btw en esa ruta o edita esta linea del .bat:',
            '  echo Plantillas esperadas:',
            '  echo C:\\POS\\BarTender\\etiquetas_2x50x25_con_precio.btw',
            '  echo C:\\POS\\BarTender\\etiquetas_2x50x25_sin_precio.btw',
            '  echo C:\\POS\\BarTender\\etiquetas_3x32x25_con_precio.btw',
            '  echo C:\\POS\\BarTender\\etiquetas_3x32x25_sin_precio.btw',
            '  pause',
            '  exit /b 1',
            ')',
            '',
            'if "%BARTENDER_EXE%"=="" (',
            '  echo No se encontro bartend.exe.',
            '  echo Edita este .bat y coloca la ruta correcta de BarTender.',
            '  pause',
            '  exit /b 1',
            ')',
            '',
            'if exist "%B64_FILE%" del "%B64_FILE%"',
        ];

        foreach (str_split($csvBase64, 500) as $chunk) {
            $lineasBat[] = 'echo ' . $chunk . '>>"%B64_FILE%"';
        }

        $lineasBat = array_merge($lineasBat, [
            '',
            'powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $b64 = (Get-Content -Raw $env:B64_FILE) -replace \'\s\',\'\'; [IO.File]::WriteAllBytes($env:CSV_FILE, [Convert]::FromBase64String($b64)) } catch { Write-Host \'ERROR AL CREAR EL CSV:\' $_.Exception.Message; exit 1 }"',
            '',
            'if errorlevel 1 (',
            '  echo.',
            '  echo Fallo la creacion del CSV. Mira el mensaje de error de arriba.',
            '  pause',
            '  exit /b 1',
            ')',
            '',
            'if not exist "%CSV_FILE%" (',
            '  echo.',
            '  echo El archivo CSV no se creo: %CSV_FILE%',
            '  echo Posibles causas: PowerShell bloqueado por politica de seguridad,',
            '  echo antivirus, o permisos de la carpeta temporal.',
            '  pause',
            '  exit /b 1',
            ')',
            '',
            'del "%B64_FILE%"',
            '',
            'echo Archivo de datos creado:',
            'echo %CSV_FILE%',
            'echo.',
            'echo Abriendo BarTender...',
            'start "" "%BARTENDER_EXE%" /F="%BTW_FILE%" /D="%CSV_FILE%"',
            'exit /b 0',
            '',
        ]);

        $bat = implode("\r\n", $lineasBat);

        return response()->streamDownload(function () use ($bat) {
            echo $bat;
        }, 'imprimir_bartender_' . $nombreArchivo . '.bat', [
            'Content-Type' => 'application/x-bat',
        ]);
    }
}
