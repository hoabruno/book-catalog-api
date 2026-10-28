@extends('layouts.app', ['title' => $book->exists ? 'Modifier' : 'Nouveau livre'])

@section('content')
    <h1>{{ $book->exists ? 'Modifier « '.$book->title.' »' : 'Nouveau livre' }}</h1>

    <form class="book-form" method="post"
          action="{{ $book->exists ? route('manage.books.update', $book) : route('manage.books.store') }}">
        @csrf
        @if ($book->exists)
            @method('PUT')
        @endif

        <label class="field field-wide">
            <span>Titre</span>
            <input name="title" value="{{ old('title', $book->title) }}" required maxlength="255">
            @error('title')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>Auteur</span>
            <select name="author_id" required>
                <option value="">Choisir…</option>
                @foreach ($authors as $author)
                    <option value="{{ $author->id }}" @selected((int) old('author_id', $book->author_id) === $author->id)>{{ $author->name }}</option>
                @endforeach
            </select>
            @error('author_id')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>ISBN (10 ou 13 chiffres)</span>
            <input name="isbn" value="{{ old('isbn', $book->isbn?->value) }}" required inputmode="numeric">
            @error('isbn')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>Format</span>
            <select name="format" required>
                @foreach ($formats as $value => $label)
                    <option value="{{ $value }}" @selected(old('format', $book->format?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('format')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>Langue (code ISO)</span>
            <input name="language" value="{{ old('language', $book->language ?? 'fr') }}" maxlength="2">
            @error('language')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>Année de publication</span>
            <input type="number" name="published_year" value="{{ old('published_year', $book->published_year) }}">
            @error('published_year')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field">
            <span>Nombre de pages</span>
            <input type="number" name="page_count" value="{{ old('page_count', $book->page_count) }}">
            @error('page_count')<em class="error">{{ $message }}</em>@enderror
        </label>

        <label class="field field-wide">
            <span>Résumé</span>
            <textarea name="summary" rows="6" maxlength="5000">{{ old('summary', $book->summary) }}</textarea>
            @error('summary')<em class="error">{{ $message }}</em>@enderror
        </label>

        <div class="actions field-wide">
            <button class="button" type="submit">{{ $book->exists ? 'Enregistrer' : 'Ajouter au catalogue' }}</button>
            <a class="link" href="{{ $book->exists ? route('catalog.show', $book) : route('catalog.index') }}">Annuler</a>
        </div>
    </form>
@endsection
