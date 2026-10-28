@extends('layouts.app', ['title' => $book->title])

@section('content')
    <p class="breadcrumb"><a href="{{ route('catalog.index') }}">Catalogue</a> / {{ $book->author->name }}</p>

    <article class="book-detail">
        <div class="cover" style="--hue: {{ crc32($book->author->name) % 360 }}" aria-hidden="true">
            <span class="cover-title">{{ $book->title }}</span>
            <span class="cover-author">{{ $book->author->name }}</span>
        </div>

        <div>
            <h1>{{ $book->title }}</h1>
            <p class="byline">{{ $book->author->name }}
                @if ($book->author->birth_year)
                    <span class="muted">({{ $book->author->birth_year }}–{{ $book->author->death_year ?? '' }})</span>
                @endif
            </p>

            @if ($book->summary)
                <p class="summary">{{ $book->summary }}</p>
            @endif

            <dl class="facts">
                <div><dt>ISBN</dt><dd>{{ $book->isbn->formatted() }}</dd></div>
                <div><dt>Format</dt><dd>{{ $book->format->label() }}</dd></div>
                <div><dt>Publication</dt><dd>{{ $book->published_year ?? 'Inconnue' }}</dd></div>
                <div><dt>Pages</dt><dd>{{ $book->page_count ?? '?' }}</dd></div>
                <div><dt>Langue</dt><dd>{{ strtoupper($book->language) }}</dd></div>
            </dl>

            @can('update', $book)
                <div class="actions">
                    <a class="button button-ghost" href="{{ route('manage.books.edit', $book) }}">Modifier</a>
                    <form method="post" action="{{ route('manage.books.destroy', $book) }}" class="inline"
                          data-confirm="Retirer ce livre du catalogue ?">
                        @csrf
                        @method('DELETE')
                        <button class="button button-danger" type="submit">Retirer</button>
                    </form>
                </div>
            @endcan
        </div>
    </article>

    <section class="files">
        <h2>Fichiers</h2>

        @if ($book->files->isEmpty())
            <p class="empty">Aucun fichier attaché.</p>
        @else
            <table class="file-table">
                <thead>
                    <tr><th>Nom</th><th>Taille</th><th>Stocké sur</th><th>SHA-256</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($book->files as $file)
                        <tr>
                            <td><a href="{{ route('files.download', $file) }}">{{ $file->original_name }}</a></td>
                            <td>{{ Number::fileSize($file->size, precision: 1) }}</td>
                            <td>
                                @foreach ($file->locations() as $location)
                                    <span class="badge {{ $location === $file->backend ? 'badge-primary' : '' }}"
                                          title="{{ $location === $file->backend ? 'Copie principale' : 'Réplique vérifiée' }}">{{ $location }}</span>
                                @endforeach
                                @if ($file->replicas === [])
                                    <span class="muted small">réplication en attente</span>
                                @endif
                            </td>
                            <td><code title="{{ $file->checksum }}">{{ Str::limit($file->checksum, 12, '…') }}</code></td>
                            <td>
                                @can('manageFiles', $book)
                                    <form method="post" action="{{ route('manage.files.destroy', $file) }}" class="inline"
                                          data-confirm="Supprimer ce fichier de tous les stockages ?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="link link-danger" type="submit">Supprimer</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('manageFiles', $book)
            <form class="upload" method="post" action="{{ route('manage.books.files.store', $book) }}" enctype="multipart/form-data">
                @csrf
                <label class="field field-wide">
                    <span>Ajouter un fichier ({{ implode(', ', config('catalog.files.mimes')) }} ; {{ intdiv(config('catalog.files.max_kilobytes'), 1024) }} Mo max)</span>
                    <input type="file" name="file" required>
                </label>
                <label class="field">
                    <span>Stockage principal</span>
                    <select name="backend">
                        @foreach ($backends as $backend)
                            <option value="{{ $backend }}" @selected($backend === config('catalog.storage.default'))>{{ $backend }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">Envoyer</button>
                @error('file')<p class="error">{{ $message }}</p>@enderror
                @error('backend')<p class="error">{{ $message }}</p>@enderror
            </form>
        @endcan
    </section>
@endsection
