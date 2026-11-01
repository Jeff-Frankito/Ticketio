@extends('layouts.!master')

@section('title', 'Reset Password - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">

            <h2 class="mb-2">Reset your password</h2>
            <p class="text-muted mb-4">
              Choose a new password for your Ticketio account.
            </p>

            @if($error ?? false)
              <div class="alert alert-danger" role="alert">
                {{ $error }}
              </div>
            @endif

            <form method="post" action="/reset-password">
              <input type="hidden" name="_token" value="{{ $csrf }}">
              <input type="hidden" name="token" value="{{ $token }}">

              <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input type="password" class="form-control" id="password"
                       name="password" minlength="12" maxlength="72"
                       autocomplete="new-password" required autofocus>
                <div class="form-text">Use a password between 12 and 72 bytes.</div>
              </div>

              <div class="mb-4">
                <label for="password_confirmation" class="form-label">
                  Confirm new password
                </label>
                <input type="password" class="form-control"
                       id="password_confirmation"
                       name="password_confirmation"
                       autocomplete="new-password" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                Reset password
              </button>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
