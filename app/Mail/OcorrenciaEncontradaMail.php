<?php

namespace App\Mail;

use App\Models\Empresa;
use App\Models\Diario;
use App\Models\Ocorrencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OcorrenciaEncontradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Empresa $empresa,
        public Diario $diario,
        public ?Ocorrencia $ocorrencia = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 {$this->empresa->nome} encontrada em Diário Oficial - {$this->diario->estado}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ocorrencia-encontrada',
            with: [
                'empresa' => $this->empresa,
                'diario' => $this->diario,
                'ocorrencia' => $this->ocorrencia,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}