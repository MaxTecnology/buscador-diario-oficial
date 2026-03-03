<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiarioController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\IngestaoDiarioController;
use App\Http\Controllers\Api\OcorrenciaController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas da API do sistema.
|
*/

// Rotas publicas de autenticacao
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Ingestao automatica (n8n -> webhook)
Route::post('v1/ingest/diarios', IngestaoDiarioController::class)
    ->middleware(['throttle:60,1']);

// Rotas protegidas por autenticacao
Route::middleware(['auth:sanctum', 'audit'])->group(function () {

    // Autenticacao
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('refresh-token', [AuthController::class, 'refreshToken']);
        Route::get('verify', [AuthController::class, 'verify']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Dashboard e metricas
    Route::prefix('dashboard')->group(function () {
        Route::get('metrics', [DashboardController::class, 'metrics']);
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('recent-activity', [DashboardController::class, 'recentActivity']);
    });

    // Usuarios - CRUD completo com permissoes
    Route::prefix('users')->middleware('role:admin,manager')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('role:admin,manager');
        Route::get('{user}', [UserController::class, 'show']);
        Route::put('{user}', [UserController::class, 'update']);
        Route::delete('{user}', [UserController::class, 'destroy'])->middleware('role:admin');

        // Gestao de empresas do usuario
        Route::post('{user}/empresas', [UserController::class, 'attachEmpresa']);
        Route::delete('{user}/empresas/{empresa}', [UserController::class, 'detachEmpresa']);
        Route::put('{user}/empresas/{empresa}/permissions', [UserController::class, 'updateEmpresaPermissions']);
    });

    // Empresas - CRUD com busca e filtros
    Route::prefix('empresas')->group(function () {
        Route::get('/', [EmpresaController::class, 'index']);
        Route::post('/', [EmpresaController::class, 'store'])->middleware('role:admin,manager');
        Route::get('search', [EmpresaController::class, 'search']);
        Route::post('import', [EmpresaController::class, 'import'])->middleware('role:admin,manager');
        Route::get('export', [EmpresaController::class, 'export']);

        Route::get('{empresa}', [EmpresaController::class, 'show']);
        Route::put('{empresa}', [EmpresaController::class, 'update'])->middleware('role:admin,manager');
        Route::delete('{empresa}', [EmpresaController::class, 'destroy'])->middleware('role:admin');

        // Historico de ocorrencias por empresa
        Route::get('{empresa}/ocorrencias', [EmpresaController::class, 'ocorrencias']);
        Route::get('{empresa}/stats', [EmpresaController::class, 'stats']);

        // Teste de busca em tempo real
        Route::post('{empresa}/test-search', [EmpresaController::class, 'testSearch']);
    });

    // Diarios - Upload, processamento e download
    Route::prefix('diarios')->group(function () {
        Route::get('/', [DiarioController::class, 'index']);
        Route::post('/', [DiarioController::class, 'upload'])->middleware('role:admin,manager,operator');
        Route::get('pendentes', [DiarioController::class, 'pendentes']);
        Route::get('processando', [DiarioController::class, 'processando']);
        Route::get('concluidos', [DiarioController::class, 'concluidos']);
        Route::get('com-erro', [DiarioController::class, 'comErro']);

        Route::get('{diario}', [DiarioController::class, 'show']);
        Route::get('{diario}/download', [DiarioController::class, 'download']);
        Route::post('{diario}/reprocess', [DiarioController::class, 'reprocess'])->middleware('role:admin,manager');
        Route::delete('{diario}', [DiarioController::class, 'destroy'])->middleware('role:admin');

        // Ocorrencias do diario
        Route::get('{diario}/ocorrencias', [DiarioController::class, 'ocorrencias']);
        Route::get('{diario}/stats', [DiarioController::class, 'stats']);
        Route::get('{diario}/progress', [DiarioController::class, 'progress']);
    });

    // Ocorrencias - Busca e relatorios
    Route::prefix('ocorrencias')->group(function () {
        Route::get('/', [OcorrenciaController::class, 'index']);
        Route::get('search', [OcorrenciaController::class, 'search']);
        Route::get('export', [OcorrenciaController::class, 'export']);
        Route::get('stats', [OcorrenciaController::class, 'stats']);

        Route::get('{ocorrencia}', [OcorrenciaController::class, 'show']);

        // Acoes de notificacao
        Route::post('{ocorrencia}/resend-email', [OcorrenciaController::class, 'resendEmail']);
        Route::post('{ocorrencia}/resend-whatsapp', [OcorrenciaController::class, 'resendWhatsapp']);
    });

    // Relatorios - Compliance e auditoria
    Route::prefix('relatorios')->group(function () {
        Route::get('compliance', [DashboardController::class, 'compliance'])->middleware('role:admin,manager');
        Route::get('atividades', [DashboardController::class, 'atividades'])->middleware('role:admin,manager');
        Route::get('performance', [DashboardController::class, 'performance'])->middleware('role:admin,manager');
        Route::get('empresas-resumo', [DashboardController::class, 'empresasResumo']);
        Route::get('diarios-resumo', [DashboardController::class, 'diariosResumo']);
    });

    // Configuracoes do sistema - Apenas admins
    Route::prefix('configs')->middleware('role:admin')->group(function () {
        Route::get('/', [DashboardController::class, 'configs']);
        Route::put('/', [DashboardController::class, 'updateConfigs']);
        Route::get('{chave}', [DashboardController::class, 'getConfig']);
        Route::put('{chave}', [DashboardController::class, 'setConfig']);
    });

    // Health check e monitoramento
    Route::prefix('system')->group(function () {
        Route::get('health', [DashboardController::class, 'health']);
        Route::get('queues', [DashboardController::class, 'queues'])->middleware('role:admin,manager');
        Route::get('logs', [DashboardController::class, 'logs'])->middleware('role:admin,manager');
    });
});

// Rate limiting para API
Route::middleware(['throttle:api'])->group(function () {
    // Rotas com rate limiting especifico podem ser definidas aqui
});
