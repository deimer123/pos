<?php

namespace App\Filament\Pages;

use App\Models\ActivacionLocal;
use App\Support\PosEdition;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Copias de seguridad de la edicion Local -- unica edicion que las
 * necesita (Online y la hibrida/Turion tienen al droplet como respaldo
 * real; Local no tiene ningun otro lado donde vivan los datos). El
 * respaldo es un solo ZIP descargable: base de datos + fotos. Restaurar
 * requiere reiniciar la app (el swap de archivos real lo hace Rust, ver
 * src-tauri/src/lib.rs::preparar_restauracion) porque no es seguro
 * reemplazar database.sqlite mientras el servidor PHP local tiene una
 * conexion abierta contra ese mismo archivo.
 */
class RespaldoLocal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Copias de Seguridad';
    protected static ?string $navigationGroup = 'Administración';
    protected static string $view = 'filament.pages.respaldo-local';

    public ?array $data = [];
    public bool $restauracionLista = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return PosEdition::esLocal();
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->restauracionLista = File::exists($this->manifiestoRestauracionPath());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('archivo_respaldo')
                    ->label('Archivo de respaldo (.zip)')
                    ->disk('local')
                    ->directory('pending-restore-uploads')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->required(),
            ])
            ->statePath('data');
    }

    public function descargarRespaldo()
    {
        $tmpDir = storage_path('app/tmp-respaldo');
        File::ensureDirectoryExists($tmpDir);

        $tmpDbPath = $tmpDir.'/'.Str::uuid().'.sqlite';

        // VACUUM INTO: snapshot consistente y atomico del sqlite en un
        // solo paso, sin tener que cerrar conexiones ni preocuparse por
        // archivos -wal/-shm sueltos (a diferencia de copiar el .sqlite a
        // mano mientras el servidor local sigue corriendo).
        DB::statement('VACUUM INTO ?', [$tmpDbPath]);

        $nombreZip = 'respaldo-'.now()->format('Y-m-d-His').'.zip';
        $zipPath = $tmpDir.'/'.$nombreZip;

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpDbPath, 'database.sqlite');

        $publicDir = storage_path('app/public');
        if (File::isDirectory($publicDir)) {
            $this->agregarDirectorioAlZip($zip, $publicDir, 'storage_public');
        }

        $activacion = ActivacionLocal::first();

        $zip->addFromString('manifest.json', json_encode([
            'app' => 'Sistema POS Local',
            'version' => config('pos.version'),
            'empresa_id' => $activacion?->empresa_id,
            'empresa_nombre' => $activacion?->empresa_nombre,
            'generado_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();
        File::delete($tmpDbPath);

        return response()->download($zipPath, $nombreZip)->deleteFileAfterSend(true);
    }

    public function prepararRestauracion(): void
    {
        // Se lee directo de $this->data (no $this->form->getState()): no
        // hace falta correr las reglas de validacion automaticas de
        // Filament para el FileUpload, la validacion real de "es un
        // respaldo valido de esta empresa" es la de abajo.
        $rutaSubida = $this->data['archivo_respaldo'] ?? null;

        if (! $rutaSubida) {
            Notification::make()->title('Selecciona un archivo de respaldo')->danger()->send();

            return;
        }

        $rutaZip = storage_path('app/'.$rutaSubida);

        $zip = new ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            Notification::make()->title('El archivo no es un ZIP válido')->danger()->send();
            File::delete($rutaZip);

            return;
        }

        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = $manifestJson ? json_decode($manifestJson, true) : null;

        if (! $manifest || $zip->locateName('database.sqlite') === false) {
            $zip->close();
            Notification::make()->title('El archivo no parece un respaldo válido de esta app')->danger()->send();
            File::delete($rutaZip);

            return;
        }

        $activacion = ActivacionLocal::first();

        if ($activacion && (int) ($manifest['empresa_id'] ?? 0) !== (int) $activacion->empresa_id) {
            $zip->close();
            Notification::make()
                ->title('Este respaldo pertenece a otra empresa')
                ->body('No se puede restaurar un respaldo de una instalación distinta.')
                ->danger()
                ->send();
            File::delete($rutaZip);

            return;
        }

        $destino = storage_path('app/pending-restore');
        File::deleteDirectory($destino);
        File::ensureDirectoryExists($destino);
        $zip->extractTo($destino);
        $zip->close();
        File::delete($rutaZip);

        // Marca legible por Rust en el proximo arranque/comando de
        // restauracion (preparar_restauracion en src-tauri/src/lib.rs) --
        // aca solo se deja todo listo, el swap de archivos real requiere
        // apagar el servidor PHP local primero.
        File::put($this->manifiestoRestauracionPath(), json_encode([
            'ruta' => $destino,
            'preparado_at' => now()->toIso8601String(),
        ]));

        $this->restauracionLista = true;

        Notification::make()
            ->title('Respaldo listo para restaurar')
            ->body('Hacé clic en "Reiniciar y restaurar" para completar el proceso.')
            ->success()
            ->send();
    }

    private function agregarDirectorioAlZip(ZipArchive $zip, string $dir, string $prefijoEnZip): void
    {
        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($archivos as $archivo) {
            if ($archivo->isDir()) {
                continue;
            }

            $rutaRelativa = ltrim(str_replace($dir, '', $archivo->getPathname()), '\\/');
            $zip->addFile($archivo->getPathname(), $prefijoEnZip.'/'.str_replace('\\', '/', $rutaRelativa));
        }
    }

    private function manifiestoRestauracionPath(): string
    {
        return storage_path('app/pending-restore.json');
    }
}
