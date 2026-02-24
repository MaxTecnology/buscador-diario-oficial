@php
    $brandName = $this->getBrandDisplayName();
    $brandLogo = filament()->getBrandLogo();
    $loginTagline = $this->getLoginTagline();
    $heroTitle = $this->getLoginHeroTitle();
    $heroSubtitle = $this->getLoginHeroSubtitle();
    $heroImage = $this->getLoginHeroImage();
@endphp

@push('styles')
    <style>
        .g2a-auth-login-page .fi-simple-main {
            background: transparent;
            box-shadow: none;
            border: 0;
            padding: 0;
            max-width: min(1180px, calc(100vw - 2rem));
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .g2a-auth-login-page .fi-simple-main-ctn {
            align-items: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .g2a-login-form button[type='submit'] {
            background-color: #6366f1 !important;
            border-color: #6366f1 !important;
            color: #fff !important;
            box-shadow: 0 10px 20px -12px rgba(99, 102, 241, 0.7);
        }

        .g2a-login-form button[type='submit']:hover {
            background-color: #5458ee !important;
            border-color: #5458ee !important;
        }

        .g2a-login-form input[type='email'],
        .g2a-login-form input[type='password'],
        .g2a-login-form input[type='text'] {
            border-radius: 0.65rem;
        }
    </style>
@endpush

<div class="fi-simple-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid lg:grid-cols-2">
            <div class="bg-white p-8 sm:p-10 lg:p-14 dark:bg-gray-900">
                <div class="mx-auto w-full max-w-md">
                    <div class="mb-10 flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800">
                            @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                <div class="flex items-center justify-center">
                                    {{ $brandLogo }}
                                </div>
                            @elseif (filled($brandLogo))
                                <img
                                    alt="{{ $brandName }}"
                                    src="{{ $brandLogo }}"
                                    class="h-9 w-auto object-contain"
                                >
                            @else
                                <span class="text-lg font-black text-slate-900 dark:text-white">
                                    {{ str($brandName)->substr(0, 2)->upper() }}
                                </span>
                            @endif
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                                Aplicação
                            </p>
                            <p class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ $brandName }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            {{ $this->getHeading() }}
                        </h1>

                        @if (filled($this->getSubheading()))
                            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ $this->getSubheading() }}
                            </p>
                        @endif
                    </div>

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

                    @if (filament()->hasRegistration())
                        <div class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('filament-panels::pages/auth/login.actions.register.before') }}
                            <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ $this->registerAction }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="relative hidden min-h-[640px] overflow-hidden bg-gradient-to-b from-indigo-500 to-indigo-600 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="pointer-events-none absolute -left-12 -top-14 h-32 w-32 rounded-full bg-white/20"></div>
                <div class="pointer-events-none absolute -right-10 top-10 h-28 w-28 rounded-full bg-sky-300/25"></div>
                <div class="pointer-events-none absolute -bottom-8 left-10 h-24 w-24 rounded-full bg-indigo-300/20"></div>

                <div class="relative z-10">
                    <p class="text-xs font-semibold uppercase tracking-widest text-indigo-100/90">
                        {{ $loginTagline }}
                    </p>
                </div>

                <div class="relative z-10 flex flex-1 items-center justify-center py-8">
                    @if (filled($heroImage))
                        <div class="w-full max-w-lg rounded-2xl border border-white/15 bg-white/5 p-4 shadow-2xl backdrop-blur-sm">
                            <img
                                src="{{ $heroImage }}"
                                alt="{{ $heroTitle }}"
                                class="w-full rounded-xl bg-white/95 object-contain p-3"
                            >
                        </div>
                    @else
                        <div class="w-full max-w-lg rounded-2xl border border-white/20 bg-white/10 p-8 text-center backdrop-blur-sm">
                            <p class="text-sm font-medium text-white">
                                Adicione a ilustração em <span class="font-semibold">resources/branding/login-hero.png</span>
                            </p>
                            <p class="mt-2 text-xs text-indigo-100/80">
                                Configure <span class="font-semibold">FILAMENT_LOGIN_HERO_FILE</span> no ambiente.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="relative z-10">
                    <h2 class="max-w-md text-3xl font-semibold leading-tight tracking-tight">
                        {{ $heroTitle }}
                    </h2>
                    <p class="mt-4 max-w-md text-sm leading-6 text-indigo-100">
                        {{ $heroSubtitle }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <x-filament-actions::modals />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
