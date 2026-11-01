@extends('layouts.!master')

@section('title', 'Create Account - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body p-4">

            <h2 class="mb-2">Create an account</h2>
            <p class="text-muted mb-4">Get started with Ticketio.</p>

            @if($error ?? false)
              <div class="alert alert-danger" role="alert">
                {{ $error }}
              </div>
            @endif

            <form method="post" action="/register">
              <input type="hidden" name="_token" value="{{ $csrf }}">

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="first_name" class="form-label">First name</label>
                  <input type="text" class="form-control" id="first_name"
                         name="first_name" maxlength="100"
                         autocomplete="given-name" required>
                </div>

                <div class="col-md-6 mb-3">
                  <label for="last_name" class="form-label">Last name</label>
                  <input type="text" class="form-control" id="last_name"
                         name="last_name" maxlength="100"
                         autocomplete="family-name" required>
                </div>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email"
                       name="email" maxlength="254"
                       autocomplete="email" required>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password"
                       name="password" minlength="12" maxlength="72"
                       autocomplete="new-password" required>
                <div class="form-text">Use 12 to 72 bytes. Some characters, such as emoji, use more than one byte.</div>
              </div>

              <div class="mb-4">
                <label for="password_confirmation" class="form-label">
                  Confirm password
                </label>
                <input type="password" class="form-control"
                       id="password_confirmation"
                       name="password_confirmation"
                       autocomplete="new-password" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                Create account
              </button>
            </form>

            <p class="text-center mt-4 mb-0">
              Already have an account?
              <a href="/login">Sign in</a>
            </p>

          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
