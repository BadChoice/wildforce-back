<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WildForce - Entrenament sense límits</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Carrega Tailwind CSS directament per garantir que els estils s'apliquin immediatament -->
    <script src="https://cdn.tailwindcss.com"></script>

    @fonts

    <style>
        .text-gradient {
            background: linear-gradient(135deg, #232227 0%, #45434C 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col justify-between antialiased selection:bg-[#232227] selection:text-white">

<!-- HEADER / NAVIGATION -->
<header class="w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
    <!-- Logo WildForce -->
    <div class="flex items-center gap-3">
        <x-app-logo />
    </div>

    <!-- Auth Navigation (Manté la lògica de Laravel) -->
    @if (Route::has('login'))
        <nav class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="px-5 py-2 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 shadow-sm transition-all text-sm font-semibold text-slate-800">
                    Panell de control
                </a>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">
                    Inicia sessió
                </a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="px-5 py-2 rounded-xl bg-[#232227] hover:bg-[#34333a] text-white font-semibold text-sm shadow-md shadow-[#232227]/20 transition-all">
                        Registra't
                    </a>
                @endif
            @endauth
        </nav>
    @endif
</header>

<!-- HERO SECTION -->
<main class="w-full max-w-7xl mx-auto px-6 py-12 lg:py-20 flex flex-col lg:flex-row items-center gap-12 flex-1">

    <!-- Text Principal / CTA -->
    <div class="flex-1 text-center lg:text-left space-y-6">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#232227]/10 border border-[#232227]/20 text-[#232227] text-xs font-semibold tracking-wide uppercase">
            ⚡ L'App d'Entrenament Definitiva
        </div>

        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-none text-slate-900">
            ALLIBERA LA TEVA <br class="hidden sm:inline" />
            <span class="text-gradient">FORÇA SALVATGE</span>
        </h1>

        <p class="text-slate-600 text-lg sm:text-xl max-w-2xl mx-auto lg:mx-0 leading-relaxed font-normal">
            Personalitza les teves rutines, millora les teves marques i porta el teu rendiment físic al següent nivell amb el millor seguiment d'entrenaments.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-4">
            @auth
                <a href="{{ route('dashboard') }}" class="px-8 py-4 rounded-xl bg-[#232227] hover:bg-[#34333a] text-white font-bold text-lg shadow-lg shadow-[#232227]/20 transition-all text-center">
                    Anar al teu Panell
                </a>
            @else
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="px-8 py-4 rounded-xl bg-[#232227] hover:bg-[#34333a] text-white font-bold text-lg shadow-lg shadow-[#232227]/20 transition-all text-center">
                        Comença Ara
                    </a>
                @endif
                <!-- Enllaç directament connectat amb l'id="features" per fer scroll suau -->
                <a href="#features" class="px-8 py-4 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-800 font-semibold text-lg shadow-sm transition-all text-center">
                    Descobreix més
                </a>
            @endauth
        </div>
    </div>

    <!-- Banner / Visual WildForce (Estil Flux Card) -->
    <div class="flex-1 w-full max-w-md lg:max-w-none">
        <div class="relative rounded-3xl bg-white p-8 border border-slate-200 shadow-xl shadow-slate-200/50 overflow-hidden group">
            <!-- Efecte de llum posterior subtil -->
            <div class="absolute -top-20 -right-20 w-60 h-60 bg-[#232227]/10 rounded-full blur-3xl group-hover:bg-[#232227]/15 transition-all duration-500"></div>

            <div class="relative z-10 space-y-5">
                <!-- Targeta d'Exemple d'Entrenament -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-[#232227]/10 text-[#232227] text-xl">🏋️‍♂️</div>
                        <div>
                            <p class="text-xs font-medium text-slate-500">Sessió d'avui</p>
                            <p class="font-bold text-slate-900">Força & Hipertròfia</p>
                        </div>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">Completat</span>
                </div>

                <!-- Mètrics d'Exemple -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-sm">
                        <p class="text-xs font-medium text-slate-500">Pes Aixecat</p>
                        <p class="text-2xl font-black text-gradient">12,450 kg</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-sm">
                        <p class="text-xs font-medium text-slate-500">Rècord Personal</p>
                        <p class="text-2xl font-black text-slate-900">140 kg <span class="text-xs text-slate-400 font-normal">SBD</span></p>
                    </div>
                </div>

                <!-- Progress Bar Simulat -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-sm space-y-2">
                    <div class="flex justify-between text-xs font-medium">
                        <span class="text-slate-500">Objectiu Setmanal</span>
                        <span class="text-[#232227] font-bold">4 de 5 Dies</span>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full bg-[#232227] w-[80%] rounded-full"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- FEATURES SECTION -->
<section id="features" class="w-full max-w-7xl mx-auto px-6 py-20 border-t border-slate-200 scroll-mt-6">
    <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
            Tot el que necessites per transformar el teu entrenament
        </h2>
        <p class="text-slate-600 text-base sm:text-lg">
            Dissenyat per ajudar-te a superar els teus límits de manera intel·ligent, constant i basada en evidència.
        </p>
    </div>

    <!-- Grid amb les 7 característiques principals -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

        <!-- Feature 1 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                🧬
            </div>
            <h3 class="text-xl font-bold text-slate-900">Plans basats en la ciència</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Plans totalment adaptats i personalitzats, basats en l'última evidència científica de l'entrenament.
            </p>
        </div>

        <!-- Feature 2 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                🎯
            </div>
            <h3 class="text-xl font-bold text-slate-900">Novells i avançats</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Interfície i programes que s'adapten perfectament tant si estàs començant com si ets un atleta experimentat.
            </p>
        </div>

        <!-- Feature 3 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                🥗
            </div>
            <h3 class="text-xl font-bold text-slate-900">Nutrició personalitzada</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Plans de nutrició adaptats a la teva despesa energètica i als teus objectius específics d'entrenament.
            </p>
        </div>

        <!-- Feature 4 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                📈
            </div>
            <h3 class="text-xl font-bold text-slate-900">Avaluació de progrés constant</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Mètrics detallades i seguiment en temps real de les teves marques, volum de càrrega i evolució.
            </p>
        </div>

        <!-- Feature 5 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                ✨
            </div>
            <h3 class="text-xl font-bold text-slate-900">Interfície intuïtiva i fàcil</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Amb l'estètica moderna de Flux UI per registrar els teus entrenaments ràpidament sense distraccions.
            </p>
        </div>

        <!-- Feature 6 -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-3 hover:border-[#232227]/40 transition-all hover:shadow-md">
            <div class="w-12 h-12 rounded-2xl bg-[#232227]/10 flex items-center justify-center text-2xl text-[#232227]">
                🤝
            </div>
            <h3 class="text-xl font-bold text-slate-900">Gestor d'entrenadors</h3>
            <p class="text-slate-600 text-sm leading-relaxed">
                Plataforma integrada per a entrenadors personals per gestionar els seus atletes de manera professional.
            </p>
        </div>

        <!-- Feature 7 (Especial de doble amplada en pantalles grans) -->
        <div class="p-6 rounded-3xl bg-[#232227] text-white shadow-md space-y-3 md:col-span-2 lg:col-span-3 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="space-y-2 text-center sm:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-slate-200">
                    📱 Multidispositiu
                </div>
                <h3 class="text-2xl font-bold text-white">Disponible per a iOS i Android</h3>
                <p class="text-slate-300 text-sm max-w-xl">
                    Porta la teva rutina a la tija de la mà. Sincronització instantània entre el teu mòbil i la teva plataforma web.
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="px-5 py-3 rounded-2xl bg-white/10 border border-white/10 font-semibold text-sm flex items-center gap-2">
                    <span></span> App Store
                </span>
                <span class="px-5 py-3 rounded-2xl bg-white/10 border border-white/10 font-semibold text-sm flex items-center gap-2">
                    <span>🤖</span> Google Play
                </span>
            </div>
        </div>

    </div>
</section>

<!-- FOOTER -->
<footer class="w-full border-t border-slate-200 py-8 text-center text-xs text-slate-500">
    <p>&copy; {{ date('Y') }} WildForce App. Tots els drets reservats.</p>
</footer>

</body>
</html>