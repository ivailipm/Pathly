<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Welcome')])
    </head>
    <body class="min-h-screen bg-paper text-ink antialiased">
        <div class="mx-auto flex min-h-svh w-full max-w-[1120px] flex-col px-4 py-6 sm:px-10">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <span class="flex items-center gap-2.5">
                    <x-app-logo-icon class="size-7 text-lg" />
                    <span class="text-sm font-semibold">{{ config('app.name', 'Pathly') }}</span>
                </span>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-2">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary">{{ __('Today') }}</flux:button>
                        @else
                            <flux:button :href="route('login')" variant="ghost">{{ __('Log in') }}</flux:button>

                            @if (Route::has('register'))
                                <flux:button :href="route('register')" variant="primary">{{ __('Register') }}</flux:button>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="flex flex-1 flex-col justify-center gap-6 py-16">
                <x-eyebrow class="border-b-2 border-ink pb-2.5">{{ __('A learning-path tracker') }}</x-eyebrow>

                <h1 class="font-serif text-[clamp(44px,7vw,88px)] leading-[1.02] tracking-[-0.02em] text-balance">
                    {{ __('Finish what you') }} <em>{{ __('start.') }}</em>
                </h1>

                <p class="max-w-[680px] font-serif text-[22px] leading-[1.4] text-secondary text-pretty">
                    {{ __('Sketch a path for each subject, work through it one milestone at a time, and keep notes on what you learn along the way.') }}
                </p>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
