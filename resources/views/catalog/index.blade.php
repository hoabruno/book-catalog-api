@extends('layouts.app')

@section('content')
    <section class="intro">
        <h1>Catalogue de livres</h1>
        <p class="lead">Recherche plein texte sur les titres, résumés et auteurs. Les fichiers attachés aux livres sont stockés sur S3 et répliqués sur WebDAV.</p>
    </section>

    <form class="filters" method="get" action="{{ route('catalog.index') }}" role="search">
        <label class="field field-wide">
            <span>Recherche</span>
            <input type="search" name="q" value="{{ $filters->search }}" placeholder="Titre, auteur, mot du résumé…" maxlength="100">
        </label>
        <label class="field">
            <span>Auteur</span>
            <select name="author_id">
                <option value="">Tous</option>
                @foreach ($authors as $author)
                    <option value="{{ $author->id }}" @selected($filters->authorId === $author->id)>{{ $author->name }} ({{ $author->books_count }})</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Format</span>
            <select name="format">
                <option value="">Tous</option>
                @foreach ($formats as $value => $label)
                    <option value="{{ $value }}" @selected($filters->format?->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="field field-years">
            <span>Publication</span>
            <div class="years">
                <input type="number" name="year_from" value="{{ $filters->yearFrom }}" placeholder="de" min="1450" max="2100" aria-label="Année minimale">
                <input type="number" name="year_to" value="{{ $filters->yearTo }}" placeholder="à" min="1450" max="2100" aria-label="Année maximale">
            </div>
        </div>
        <div class="filter-actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="link" href="{{ route('catalog.index') }}">Réinitialiser</a>
        </div>
    </form>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    @if ($books->isEmpty())
        <p class="empty">Aucun livre ne correspond à cette recherche.</p>
    @else
        <ul class="book-grid">
            @foreach ($books as $book)
                <li>
                    <a class="book-card" href="{{ route('catalog.show', $book) }}">
                        <span class="book-spine" style="--hue: {{ crc32($book->author->name) % 360 }}" aria-hidden="true"></span>
                        <span class="book-body">
                            <span class="book-title">{{ $book->title }}</span>
                            <span class="book-author">{{ $book->author->name }}</span>
                            <span class="book-meta">
                                {{ $book->published_year ?? 's. d.' }} · {{ $book->format->label() }}
                                @if ($book->files_count > 0)
                                    · {{ $book->files_count }} {{ Str::plural('fichier', $book->files_count) }}
                                @endif
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <nav class="pager" aria-label="Pagination">
            @if ($books->previousPageUrl())
                <a class="button button-ghost" href="{{ $books->previousPageUrl() }}">Page précédente</a>
            @endif
            @if ($books->nextPageUrl())
                <a class="button button-ghost" href="{{ $books->nextPageUrl() }}">Page suivante</a>
            @endif
        </nav>
    @endif
@endsection
