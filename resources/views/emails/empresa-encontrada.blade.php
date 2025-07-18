<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Encontrada - Diário Oficial</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #e74c3c;
            margin: 0;
            font-size: 24px;
        }
        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 5px solid #f39c12;
        }
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #2c3e50;
            padding: 8px 15px 8px 0;
            width: 30%;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            padding: 8px 0;
            vertical-align: top;
        }
        .score-high { color: #27ae60; font-weight: bold; }
        .score-medium { color: #f39c12; font-weight: bold; }
        .score-low { color: #e74c3c; font-weight: bold; }
        .context-box {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            font-style: italic;
            border-radius: 0 5px 5px 0;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #7f8c8d;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-cnpj { background-color: #e74c3c; color: white; }
        .badge-nome { background-color: #f39c12; color: white; }
        .badge-termo { background-color: #9b59b6; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 EMPRESA ENCONTRADA</h1>
            <p><strong>{{ $appName }}</strong></p>
        </div>

        <div class="alert-box">
            <strong>Atenção!</strong> A empresa <strong>{{ $empresa->nome }}</strong> foi encontrada no Diário Oficial de {{ strtoupper($diario->estado) }} na data de {{ $diario->data_diario->format('d/m/Y') }}.
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">📋 Empresa:</div>
                <div class="info-value"><strong>{{ $empresa->nome }}</strong></div>
            </div>
            
            @if($empresa->cnpj)
            <div class="info-row">
                <div class="info-label">🏢 CNPJ:</div>
                <div class="info-value">{{ $empresa->cnpj }}</div>
            </div>
            @endif

            <div class="info-row">
                <div class="info-label">📅 Data do Diário:</div>
                <div class="info-value">{{ $diario->data_diario->format('d/m/Y') }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">🏛️ Estado:</div>
                <div class="info-value">{{ strtoupper($diario->estado) }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">🎯 Tipo de Match:</div>
                <div class="info-value">
                    @if($ocorrencia->tipo_match === 'cnpj')
                        <span class="badge badge-cnpj">CNPJ</span>
                    @elseif($ocorrencia->tipo_match === 'nome')
                        <span class="badge badge-nome">Nome</span>
                    @else
                        <span class="badge badge-termo">Termo Personalizado</span>
                    @endif
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">📊 Confiança:</div>
                <div class="info-value">
                    @php
                        $score = $ocorrencia->score_confianca * 100;
                        $class = $score >= 95 ? 'score-high' : ($score >= 85 ? 'score-medium' : 'score-low');
                    @endphp
                    <span class="{{ $class }}">{{ number_format($score, 1) }}%</span>
                    <small>({{ $ocorrencia->confianca_descricao }})</small>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">🔍 Termo Encontrado:</div>
                <div class="info-value"><strong>"{{ $ocorrencia->termo_encontrado }}"</strong></div>
            </div>

            @if($ocorrencia->pagina)
            <div class="info-row">
                <div class="info-label">📄 Página:</div>
                <div class="info-value">{{ $ocorrencia->pagina }}</div>
            </div>
            @endif
        </div>

        <h3>📄 Contexto da Ocorrência:</h3>
        <div class="context-box">
            {{ $ocorrencia->contexto_completo }}
        </div>

        <div style="text-align: center;">
            <a href="{{ $dashboardUrl }}" class="button">
                🔗 Acessar Sistema Completo
            </a>
        </div>

        <div class="footer">
            <p>
                <strong>{{ $appName }}</strong><br>
                Este é um email automático do sistema de monitoramento de Diários Oficiais.<br>
                Você está recebendo este email porque tem permissão para acompanhar a empresa <strong>{{ $empresa->nome }}</strong>.<br>
                <small>Email enviado em {{ now()->format('d/m/Y H:i:s') }}</small>
            </p>
            
            <p style="margin-top: 15px; font-size: 11px; color: #95a5a6;">
                <strong>IMPORTANTE:</strong> Este sistema monitora informações públicas dos Diários Oficiais estaduais.<br>
                Todas as ocorrências são registradas para fins de compliance e auditoria.
            </p>
        </div>
    </div>
</body>
</html>