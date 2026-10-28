<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ isset($title) ? $title.' · ' : '' }}Catalogue de livres</title>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="{{ route('catalog.index') }}">
                <span class="brand-mark" aria-hidden="true"></span>
                Catalogue
            </a>
            <nav class="nav">
                <a href="{{ route('catalog.index') }}">Livres</a>
                <a href="{{ url('/docs/api') }}">API</a>
                @auth
                    @can('create', App\Models\Book::class)
                        <a class="button button-small" href="{{ route('manage.books.create') }}">Ajouter un livre</a>
                    @endcan
                    <form method="post" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button class="link" type="submit">Déconnexion ({{ auth()->user()->name }})</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Connexion</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="wrap main">
        @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap footer-inner">
            <span>Démo Laravel 13 · PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} · MySQL · Redis · S3 · WebDAV</span>
            <a href="{{ url('/docs/api') }}">Documentation de l'API</a>
        </div>
    </footer>
</body>
</html>
