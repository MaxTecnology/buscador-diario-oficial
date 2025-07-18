<?php

use Illuminate\Support\Facades\Route;
use App\Models\Diario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/diarios/{diario}/download', function (Diario $diario) {
    if (!$diario->caminho_arquivo || !Storage::disk('public')->exists($diario->caminho_arquivo)) {
        abort(404, 'Arquivo não encontrado');
    }
    
    // Garantir que o nome do arquivo tenha a extensão .pdf
    $nomeArquivo = $diario->nome_arquivo;
    if (!str_ends_with(strtolower($nomeArquivo), '.pdf')) {
        $nomeArquivo .= '.pdf';
    }
    
    return Storage::disk('public')->download($diario->caminho_arquivo, $nomeArquivo);
})->name('diarios.download')->middleware('auth');
