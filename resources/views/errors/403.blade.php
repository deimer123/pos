<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso no autorizado</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1a202c;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            text-align: center;
            padding: 2rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }

        h1 {
            font-size: 6rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #e53e3e;
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        p {
            font-size: 1rem;
            color: #4a5568;
        }

        a {
            margin-top: 2rem;
            display: inline-block;
            background-color: #3182ce;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
        }

        a:hover {
            background-color: #2b6cb0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Acceso no autorizado</h2>
        <p>No tienes permisos para acceder a esta sección de la aplicación.</p>

        @auth
            <a href="{{ route('eleccion') }}">Volver al inicio</a>
        @else
            <a href="{{ route('login') }}">Iniciar sesión</a>
        @endauth
    </div>
</body>
</html>
