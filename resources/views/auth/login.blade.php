@extends('layouts.app', ['title' => 'Connexion'])

@section('content')
    <section class="login">
        <h1>Connexion</h1>
        <p class="muted">Réservée aux comptes éditeur pour gérer le catalogue. La consultation est libre.</p>

        <form method="post" action="{{ route('login') }}">
            @csrf
            <label class="field">
                <span>E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </label>
            <label class="field">
                <span>Mot de passe</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="checkbox"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
            @error('email')<p class="error">{{ $message }}</p>@enderror
            <button class="button" type="submit">Se connecter</button>
        </form>
    </section>
@endsection
