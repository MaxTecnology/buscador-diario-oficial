<div class="space-y-4">
    <div class="grid md:grid-cols-2 gap-4">
        <div class="space-y-1">
            <div class="text-sm text-gray-500">Empresa</div>
            <div class="text-base font-semibold">{{ $record->empresa->nome ?? '-' }}</div>
            <div class="text-sm text-gray-500">Diário</div>
            <div class="text-sm">{{ $record->diario->nome_arquivo ?? '-' }}</div>
            <div class="text-sm text-gray-500">Estado / Data</div>
            <div class="text-sm">{{ $record->diario->estado ?? '-' }} • {{ optional($record->diario->data_diario)->format('d/m/Y') }}</div>
        </div>
        <div class="space-y-1">
            <div class="text-sm text-gray-500">Termo / Tipo</div>
            <div class="text-base font-semibold">{{ $record->termo_encontrado ?? '-' }} <span class="text-xs text-gray-500">({{ strtoupper($record->tipo_match) }})</span></div>
            <div class="text-sm text-gray-500">Score / Página</div>
            <div class="text-sm">{{ number_format($record->score_confianca * 100, 1) }}% • pág. {{ $record->pagina ?? '-' }}</div>
            <div class="text-sm text-gray-500">Posições</div>
            <div class="text-sm">{{ $record->posicao_inicio }} - {{ $record->posicao_fim }}</div>
        </div>
    </div>

    <div class="space-y-2">
        <div class="text-sm font-semibold">Contexto</div>
        <div class="text-sm leading-relaxed bg-gray-50 p-3 rounded border" style="max-height: 200px; overflow:auto">
            {!! $contextoHtml !!}
        </div>
    </div>

    <div class="space-y-2">
        <div class="text-sm font-semibold">PDF (página {{ $record->pagina ?? '-' }})</div>
        @if($pdfUrl)
            <iframe src="{{ $pdfUrl }}" class="w-full" style="height:480px;"></iframe>
        @else
            <div class="text-sm text-gray-500">Arquivo PDF não disponível.</div>
        @endif
    </div>

    <div class="flex gap-2">
        @if($pdfUrl)
            <a class="filament-button filament-button-size-sm" href="{{ $pdfUrl }}" target="_blank">Abrir PDF em nova aba</a>
        @endif
    </div>
</div>
