<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Encontrada em Diário Oficial</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e74c3c;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #e74c3c;
            margin: 0;
            font-size: 24px;
        }
        .alert {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #f39c12;
        }
        .empresa-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #495057;
            min-width: 120px;
        }
        .value {
            color: #212529;
            text-align: right;
        }
        .diario-info {
            background-color: #e8f4f8;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 12px;
        }
        .score-bar {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }
        .score-fill {
            height: 100%;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Empresa Encontrada em Diário Oficial</h1>
            <p>Notificação automática do Sistema de Monitoramento</p>
        </div>

        <div class="alert">
            <strong>⚠️ Atenção:</strong> A empresa {{ $empresa->nome }} foi encontrada em um diário oficial recente.
        </div>

        <div class="empresa-info">
            <h3 style="margin-top: 0; color: #495057;">📋 Informações da Empresa</h3>
            
            <div class="info-row">
                <span class="label">Nome:</span>
                <span class="value">{{ $empresa->nome }}</span>
            </div>
            
            @if($empresa->cnpj)
            <div class="info-row">
                <span class="label">CNPJ:</span>
                <span class="value">{{ $empresa->cnpj_formatado ?? $empresa->cnpj }}</span>
            </div>
            @endif
            
            @if($empresa->inscricao_estadual)
            <div class="info-row">
                <span class="label">Inscrição Estadual:</span>
                <span class="value">{{ $empresa->inscricao_estadual }}</span>
            </div>
            @endif
        </div>

        <div class="diario-info">
            <h3 style="margin-top: 0; color: #17a2b8;">📄 Detalhes da Ocorrência</h3>
            
            <div class="info-row">
                <span class="label">Diário:</span>
                <span class="value">{{ $diario->nome_arquivo }}</span>
            </div>
            
            <div class="info-row">
                <span class="label">Estado:</span>
                <span class="value">{{ $diario->estado }}</span>
            </div>
            
            <div class="info-row">
                <span class="label">Data do Diário:</span>
                <span class="value">{{ $diario->data_diario?->format('d/m/Y') ?? 'Não informado' }}</span>
            </div>
            
            @if(isset($ocorrencia))
            <div class="info-row">
                <span class="label">Tipo de Match:</span>
                <span class="value">{{ ucfirst($ocorrencia->tipo_match) }}</span>
            </div>
            
            <div class="info-row">
                <span class="label">Página:</span>
                <span class="value">{{ $ocorrencia->pagina ?? 'N/A' }}</span>
            </div>
            
            <div class="info-row">
                <span class="label">Score de Confiança:</span>
                <span class="value">{{ number_format($ocorrencia->score, 1) }}%</span>
            </div>
            
            @php
                $scoreColor = $ocorrencia->score >= 90 ? '#28a745' : 
                             ($ocorrencia->score >= 70 ? '#ffc107' : '#dc3545');
            @endphp
            
            <div class="score-bar">
                <div class="score-fill" style="width: {{ $ocorrencia->score }}%; background-color: {{ $scoreColor }};">
                    {{ number_format($ocorrencia->score, 1) }}%
                </div>
            </div>
            
            @if($ocorrencia->texto_match)
            <div style="margin-top: 15px;">
                <strong>Texto encontrado:</strong>
                <div style="background-color: #fff; padding: 10px; border-radius: 3px; margin-top: 5px; font-family: monospace; font-size: 12px; border-left: 3px solid #007bff;">
                    "{{ Str::limit($ocorrencia->texto_match, 200) }}"
                </div>
            </div>
            @endif
            @endif
        </div>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}" class="button">
                🔗 Acessar Sistema Completo
            </a>
        </div>

        <div style="background-color: #d1ecf1; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #0c5460;">💡 Próximos Passos</h4>
            <ul style="margin-bottom: 0;">
                <li>Acesse o sistema para ver todos os detalhes</li>
                <li>Verifique se há outras ocorrências desta empresa</li>
                <li>Analise o contexto completo da publicação</li>
                <li>Entre em contato com a empresa se necessário</li>
            </ul>
        </div>

        <div class="footer">
            <p>Este email foi enviado automaticamente pelo Sistema de Monitoramento de Diários Oficiais.<br>
            Horário de envio: {{ now()->format('d/m/Y H:i:s') }}</p>
            
            <p style="margin-top: 15px;">
                <strong>Sistema de Diários Oficiais</strong><br>
                Email: {{ config('mail.from.address') }}<br>
                🔒 Informações confidenciais - Não repasse este email
            </p>
        </div>
    </div>
</body>
</html>