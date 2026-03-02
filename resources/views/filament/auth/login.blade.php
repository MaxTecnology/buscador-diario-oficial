@php
    $brandName = $this->getBrandDisplayName();
    $brandLogo = filament()->getBrandLogo();
@endphp

@push('styles')
    <style>
        .g2a-auth-login-page .fi-simple-main {
            background: transparent !important;
            box-shadow: none;
            border: 0;
            padding: 0;
            max-width: min(980px, calc(100vw - 2rem));
            margin: 0 auto;
        }

        .g2a-auth-login-page .fi-simple-main-ctn {
            align-items: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        .g2a-login-shell {
            display: grid;
            grid-template-columns: 1fr;
            border-radius: 1rem;
            border: 1px solid rgb(226 232 240);
            background: #fff;
            box-shadow: 0 20px 40px -24px rgba(2, 6, 23, 0.4);
            overflow: hidden;
        }

        .dark .g2a-login-shell {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgb(17 24 39);
        }

        .g2a-login-brand-pane {
            background: linear-gradient(145deg, #0284c7 0%, #1d4ed8 100%);
            color: #fff;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.25rem;
        }

        .g2a-login-brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .g2a-login-brand-logo {
            display: flex;
            height: 23rem;
            width: 23rem;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.96);
        }

        .g2a-login-brand-logo img {
            max-height: 8rem;
            width: auto;
        }

        .g2a-login-brand-name {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.2;
        }

        .g2a-login-form-pane {
            padding: 2rem;
            background: #fff;
        }

        .dark .g2a-login-form-pane {
            background: rgb(17 24 39);
        }

        .g2a-login-form-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: rgb(15 23 42);
        }

        .dark .g2a-login-form-title {
            color: #fff;
        }

        .g2a-login-form button[type='submit'] {
            background-color: #0284c7 !important;
            border-color: #0284c7 !important;
            color: #fff !important;
            box-shadow: 0 10px 20px -14px rgba(2, 132, 199, 0.75);
        }

        .g2a-login-form button[type='submit']:hover {
            background-color: #0369a1 !important;
            border-color: #0369a1 !important;
        }

        .g2a-login-form input[type='email'],
        .g2a-login-form input[type='password'],
        .g2a-login-form input[type='text'] {
            border-radius: 0.65rem;
        }

        @media (min-width: 1024px) {
            .g2a-login-shell {
                grid-template-columns: minmax(300px, 0.9fr) 1.1fr;
            }
        }
    </style>
@endpush

<div class="fi-simple-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <section class="g2a-login-shell">
        <aside class="g2a-login-brand-pane">
            <div class="g2a-login-brand">
                <h1 class="g2a-login-brand-name">{{ $brandName }}</h1>
                <div class="g2a-login-brand-logo">
                    @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                        <div class="flex items-center justify-center">
                            {{ $brandLogo }}
                        </div>
                    @elseif (filled($brandLogo))
                        <img alt="{{ $brandName }}" src="{{ $brandLogo }}">
                    @else
                        <span class="text-sm font-black text-slate-900">
                            {{ str($brandName)->substr(0, 2)->upper() }}
                        </span>
                    @endif
                </div>
            </div>
        </aside>

        <div class="g2a-login-form-pane">
            <h2 class="g2a-login-form-title">Entrar</h2>

            <div class="g2a-login-form">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

                <x-filament-panels::form id="form" wire:submit="authenticate">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
            </div>
        </div>
    </section>

    <x-filament-actions::modals />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
