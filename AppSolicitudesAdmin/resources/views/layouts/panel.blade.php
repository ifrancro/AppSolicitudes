<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · Campus Connect</title>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
@auth
    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="{{ route('panel.inicio') }}">Campus Connect</a>
            <nav class="nav" aria-label="Principal">
                @can('viewAny', \App\Models\Solicitud::class)
                    <a href="{{ route('panel.solicitudes.index') }}" @if (request()->routeIs('panel.solicitudes.index')) aria-current="page" @endif>Solicitudes</a>
                @endcan
                @can('viewAssigned', \App\Models\Solicitud::class)
                    <a href="{{ route('panel.solicitudes.asignadas') }}" @if (request()->routeIs('panel.solicitudes.asignadas')) aria-current="page" @endif>Mis asignadas</a>
                @endcan
                @stack('nav')
            </nav>
            <div class="user">
                <span>{{ auth()->user()->nombre }} · {{ str_replace('_', ' ', auth()->user()->rol->nombre) }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="secondary" type="submit">Salir</button>
                </form>
            </div>
        </div>
    </header>
@endauth

<main class="container">
    @if (session('exito'))
        <div class="alert alert--ok" role="status">{{ session('exito') }}</div>
    @endif
    @if ($errors->any() && ! View::hasSection('sin-errores-globales'))
        <div class="alert alert--error" role="alert">
            <ul style="margin:0;padding-left:18px">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('contenido')
</main>
</body>
</html>
