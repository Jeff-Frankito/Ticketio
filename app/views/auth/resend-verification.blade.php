@extends('layouts.auth-layout')

@section('title', 'Verify Email - Ticketio')

@section('content')
  <h2 class="h4 mb-3">Verify your email</h2>

  @if($registered ?? false)
    <div class="alert alert-success" role="status">
      Your account has been created. We've sent a verification link to <strong>{{ $email }}</strong>.
      Check your inbox and spam folder, then follow the link to verify your email before signing in.
    </div>
  @endif

  <p class="text-body-secondary mb-4">
    Need another verification email? Enter your email address below to request a new link.
  </p>

  @if($success ?? false)
    <div class="alert alert-success" role="status">
      If an active account needs verification, a verification email has been requested. Check your inbox and spam folder.
    </div>
  @endif

  <form method="post" action="/resend-verification">
    <input type="hidden" name="_token" value="{{ $csrf }}">

    <div class="mb-4">
      <label for="email" class="form-label">Email address</label>
      <input type="email" class="form-control" id="email"
             name="email" maxlength="254" value="{{ $email ?? '' }}"
             autocomplete="email" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      Send verification link
    </button>
  </form>

  <p class="text-center mt-4 mb-0">
    <a href="/login">Back to login</a>
  </p>
@endsection
