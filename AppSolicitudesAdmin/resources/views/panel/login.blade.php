<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Campus Connect</title>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
<main class="login">
    <div class="card">
        <h1>Campus Connect</h1>
        <p class="muted">Panel del personal</p>

        <form method="POST" action="{{ url('/login') }}" novalidate>
            @csrf
            <label>
                Correo electrónico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label>
                Contraseña
                <input type="password" name="password" autocomplete="current-password" required>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <button type="submit">Entrar</button>
        </form>
    </div>
</main>
</body>
</html>
