<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * @var view-string
     */
    protected static string $view = 'filament.auth.login';

    protected ?string $maxWidth = '7xl';

    protected array $extraBodyAttributes = [
        'class' => 'bg-slate-100 g2a-auth-login-page',
    ];

    public function getHeading(): string
    {
        return 'Faça seu login';
    }

    public function getSubheading(): ?string
    {
        return 'Acesse com seu e-mail e senha para entrar no painel.';
    }

    public function getBrandDisplayName(): string
    {
        return (string) (env('FILAMENT_BRAND_NAME') ?: config('app.name', 'Diario'));
    }

    public function getLoginTagline(): string
    {
        return (string) env(
            'FILAMENT_LOGIN_TAGLINE',
            'Acesso seguro para monitoramento de diários, ocorrências e notificações.'
        );
    }

    public function getLoginHeroTitle(): string
    {
        return (string) env('FILAMENT_LOGIN_HERO_TITLE', 'A melhor experiência de login para sua operação.');
    }

    public function getLoginHeroSubtitle(): string
    {
        return (string) env(
            'FILAMENT_LOGIN_HERO_SUBTITLE',
            'Centralize o acompanhamento operacional e mantenha o time focado no que precisa ser tratado.'
        );
    }

    public function getLoginHeroImage(): ?string
    {
        $heroImageUrl = trim((string) env('FILAMENT_LOGIN_HERO_URL', ''));

        if ($heroImageUrl !== '') {
            return $heroImageUrl;
        }

        $heroImageFile = trim((string) env('FILAMENT_LOGIN_HERO_FILE', 'resources/branding/login-hero.png'));

        return $this->inlineImageFromFile($heroImageFile);
    }

    private function inlineImageFromFile(string $relativePath): ?string
    {
        $absolutePath = base_path(ltrim($relativePath, '/'));

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mimeType === null) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }
}
