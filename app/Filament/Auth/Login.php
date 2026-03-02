<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

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

    public function getHeading(): string | Htmlable
    {
        return 'Entrar';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function getTitle(): string | Htmlable
    {
        return $this->getBrandDisplayName();
    }

    public function getBrandDisplayName(): string
    {
        $brandName = trim((string) env('FILAMENT_BRAND_NAME', ''));

        if ($brandName === '') {
            $brandName = trim((string) config('app.name', ''));
        }

        if ($brandName === '' || strcasecmp($brandName, 'Laravel') === 0) {
            return 'G2A Diario';
        }

        return $brandName;
    }
}
