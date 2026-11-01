@extends('layouts.!master')

@section('title', 'Verify Email - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4 text-center">

            <h2 class="mb-3">Verify your email address</h2>
            <p class="text-muted mb-4">
              Just one more step! Confirm your email address to activate your Ticketio account.
            </p>

            <form method="post" action="/verify-email">
              <input type="hidden" name="_token" value="{{ $csrf }}">
              <input type="hidden" name="token" value="{{ $token }}">

              <button type="submit" class="btn btn-primary w-100">
                Verify email address
              </button>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
