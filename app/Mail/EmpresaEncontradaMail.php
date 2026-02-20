<?php

namespace App\Mail;

use App\Models\Ocorrencia;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class EmpresaEncontradaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Ocorrencia $ocorrencia;
    public User $usuario;

    /**
     * Create a new message instance.
     */
    public function __construct(Ocorrencia $ocorrencia, User $usuario)
    {
        $this->ocorrencia = $ocorrencia;
        $this->usuario = $usuario;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $empresa = $this->ocorrencia->empresa;
        $estado = strtoupper($this->ocorrencia->diario->estado);
        $data = $this->ocorrencia->diario->data_diario->format('d/m/Y');
        
        return new Envelope(
            subject: "[DIÁRIO OFICIAL] {$empresa->nome} encontrada - {$estado} ({$data})",
            from: [
                'address' => config('mail.from.address'),
                'name' => SystemConfig::get('system.app_name', 'Sistema de Diários Oficiais'),
            ],
            tags: [
                'notification',
                'empresa-encontrada',
                'estado-' . strtolower($this->ocorrencia->diario->estado),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.empresa-encontrada',
            with: [
                'ocorrencia' => $this->ocorrencia,
                'usuario' => $this->usuario,
                'empresa' => $this->ocorrencia->empresa,
                'diario' => $this->ocorrencia->diario,
                'appName' => SystemConfig::get('system.app_name', 'Sistema de Diários Oficiais'),
                'dashboardUrl' => config('app.url') . '/admin',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Opcionalmente anexar o PDF completo ou página específica
        $anexarPdf = SystemConfig::get('notifications.email_attach_pdf', false);
        
        $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));

        if ($anexarPdf && $disk->exists($this->ocorrencia->diario->caminho_arquivo)) {
            $attachments[] = Attachment::fromStorageDisk(config('filesystems.diarios_disk', 'diarios'), $this->ocorrencia->diario->caminho_arquivo)
                ->as($this->ocorrencia->diario->nome_arquivo)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject($this->envelope()->subject)
                    ->view('emails.empresa-encontrada')
                    ->with([
                        'ocorrencia' => $this->ocorrencia,
                        'usuario' => $this->usuario,
                        'empresa' => $this->ocorrencia->empresa,
                        'diario' => $this->ocorrencia->diario,
                        'appName' => SystemConfig::get('system.app_name', 'Sistema de Diários Oficiais'),
                        'dashboardUrl' => config('app.url') . '/admin',
                    ]);
    }
}
