@extends('layouts.auth-layout')

@section('title', 'Login - Ticketio')

@section('content')
  <h2 class="h4 mb-2">Welcome back</h2>
  <p class="text-body-secondary mb-4">Sign in to your Ticketio account.</p>

  @if($error ?? false)
    <div class="alert alert-danger" role="alert">
      {{ $error }}
    </div>
  @endif

  <form method="post" action="/login">
    <input type="hidden" name="_token" value="{{ $csrf }}">

    <div class="mb-3">
      <label for="email" class="form-label">Email address</label>
      <input type="email" class="form-control" id="email"
             name="email" autocomplete="username"
             maxlength="254" required autofocus>
    </div>

    <div class="mb-2">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control" id="password"
             name="password" autocomplete="current-password" required>
    </div>

    <div class="text-end mb-4">
      <a href="/forgot-password" class="small text-decoration-none">
        Forgot password?
      </a>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      Sign in
    </button>
  </form>

  <div class="text-center mt-4">
    <p class="mb-2">
      Don't have an account?
      <a href="/register">Create one</a>
    </p>

    <a href="/resend-verification" class="small text-decoration-none">
      Resend verification email
    </a>
  </div>
@endsection
