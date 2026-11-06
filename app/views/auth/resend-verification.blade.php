@extends('layouts.auth-layout')

@section('title', 'Resend Verification - Ticketio')

@section('content')
  <h2 class="h4 mb-3">Verify your email</h2>

  <p class="text-body-secondary mb-4">
    Enter your email address to request a new verification link.
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
             name="email" maxlength="254"
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
