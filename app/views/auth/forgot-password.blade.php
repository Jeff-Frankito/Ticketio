@extends('layouts.auth-layout')

@section('title', 'Forgot Password - Ticketio')

@section('content')
  <h2 class="h4 mb-2">Forgot your password?</h2>

  <p class="text-body-secondary mb-4">
    Enter your email address and we'll send you a link to reset your password.
  </p>

  @if($success ?? false)
    <div class="alert alert-success" role="status">
      If an active account exists for that email address, a password reset email has been requested. Check your inbox and spam folder.
    </div>
  @endif

  <form method="post" action="/forgot-password">
    <input type="hidden" name="_token" value="{{ $csrf }}">

    <div class="mb-4">
      <label for="email" class="form-label">Email address</label>
      <input type="email" class="form-control" id="email"
             name="email" maxlength="254"
             autocomplete="email" required autofocus>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      Send reset link
    </button>
  </form>

  <p class="text-center mt-4 mb-0">
    <a href="/login">Back to login</a>
  </p>
@endsection
