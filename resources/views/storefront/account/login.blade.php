@extends('storefront.layout', ['title' => 'Sign in'])

@section('content')
<div class="container py-5" style="max-width: 460px">
    <h1 class="section-title h2 text-center mb-4">Sign in</h1>
    <form method="POST" action="{{ route('shop.account.login') }}" class="panel">
        @csrf
        <div class="mb-3"><label class="form-label" for="login">Mobile number or email</label><input id="login" name="login" class="form-control" value="{{ old('login') }}" required autofocus autocomplete="username"></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" required autocomplete="current-password"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label small" for="remember">Keep me signed in</label></div>
        <button class="btn btn-accent w-100 py-2">Sign in</button>
        <p class="small text-center mt-3 mb-0">New here? <a href="{{ route('shop.account.register') }}">Create an account</a></p>
    </form>
</div>
@endsection
