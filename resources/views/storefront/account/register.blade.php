@extends('storefront.layout', ['title' => 'Create account', 'inlineErrors' => true])

@section('content')
<div class="container py-5" style="max-width: 480px">
    <h1 class="section-title h2 text-center mb-1">Create your account</h1>
    <p class="text-muted text-center mb-4">Ordered before as a guest? Use the same mobile number and your past orders will appear.</p>
    <form method="POST" action="{{ route('shop.account.register') }}" class="panel">
        @csrf
        <div class="mb-3"><label class="form-label" for="name">Full name</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="phone">Mobile number</label><input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="98XXXXXXXX" required>@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="email">Email <span class="text-muted fw-normal">(optional)</span></label><input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
        <button class="btn btn-accent w-100 py-2">Create account</button>
        <p class="small text-center mt-3 mb-0">Already have an account? <a href="{{ route('shop.account.login') }}">Sign in</a></p>
    </form>
</div>
@endsection
