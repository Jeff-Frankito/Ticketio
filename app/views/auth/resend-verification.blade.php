@extends('layouts.!master')

@section('title', 'Resend Verification - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h2 class="mb-3">Verify your email</h2>
            <p>Enter your email address to request a new verification link.</p>
            @if($success ?? false)
              <div class="alert alert-success" role="status">
                If an active account needs verification, a verification email has been requested. Check your inbox and spam folder.
              </div>
            @endif
            <form method="post" action="/resend-verification">
              <input type="hidden" name="_token" value="{{ $csrf }}">
              <div class="mb-4">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="254" autocomplete="email" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Send verification link</button>
            </form>
            <p class="text-center mt-4 mb-0"><a href="/login">Back to login</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
