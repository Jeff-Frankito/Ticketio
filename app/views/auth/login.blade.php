@extends('layouts.!master')

@section('title', 'Login - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">

            <h2 class="mb-2">Welcome back</h2>
            <p class="text-muted mb-4">Sign in to your Ticketio account.</p>

            @if($error ?? false)
              <div class="alert alert-danger" role="alert">
                {{ $error }}
              </div>
            @endif

            @if(isset($_GET['registered']))
              <div class="alert alert-success" role="status">
                Account created successfully. Please check your email to verify your account before signing in. If the email does not arrive, request another verification link below.
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
                <a href="/forgot-password">Forgot password?</a>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                Sign in
              </button>
            </form>

            <p class="text-center mt-4 mb-0">
              Don't have an account?
              <a href="/register">Create one</a>
            </p>
            <p class="text-center mt-3 mb-0">
              <a href="/resend-verification">Resend verification email</a>
            </p>

          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
