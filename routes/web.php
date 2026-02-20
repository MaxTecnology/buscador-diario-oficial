<?php

use Illuminate\Support\Facades\Route;
use App\Models\Diario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/diarios/{diario}/arquivo', function (Request $request, Diario $diario) {
    $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));

    if (!$diario->caminho_arquivo || !$disk->exists($diario->caminho_arquivo)) {
        abort(404, 'Arquivo não encontrado');
    }

    $nomeArquivo = $diario->nome_arquivo;
    if (!str_ends_with(strtolower($nomeArquivo), '.pdf')) {
        $nomeArquivo .= '.pdf';
    }

    // Quando download=1, força download; caso contrário, exibe inline (usado nos modais/listas).
    if ($request->boolean('download')) {
        return $disk->download($diario->caminho_arquivo, $nomeArquivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    $stream = $disk->readStream($diario->caminho_arquivo);
    if (!$stream) {
        abort(404, 'Arquivo não encontrado');
    }

    return response()->stream(function () use ($stream) {
        fpassthru($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $nomeArquivo . '"',
    ]);
})->name('diarios.arquivo')->middleware('auth');
