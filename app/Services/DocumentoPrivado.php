<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoPrivado
{
    public static function guardar(UploadedFile $archivo, string $directorio): string
    {
        $extension = $archivo->guessExtension();
        $nombre = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $ruta = $archivo->storeAs($directorio, $nombre, 'documentos');

        if ($ruta === false) {
            throw new \RuntimeException('No se pudo guardar el documento.');
        }

        return $ruta;
    }

    public static function responder(string $ruta, ?string $nombre = null, bool $descargar = false): StreamedResponse
    {
        $disco = Storage::disk('documentos');

        if (!$disco->exists($ruta)) {
            // Los adjuntos históricos se sirven por las mismas rutas con autorización.
            $disco = Storage::disk('public');
        }

        abort_unless($disco->exists($ruta), 404, 'El documento no existe.');

        return $disco->response($ruta, $nombre ?? basename($ruta), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $descargar ? 'attachment' : 'inline');
    }

    public static function descartar(string $ruta): void
    {
        try {
            Storage::disk('documentos')->delete($ruta);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
