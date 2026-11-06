@extends('layouts.auth-layout')

@section('title', 'Verify Email - Ticketio')

@section('content')
  <h2 class="h4 mb-3">Verify your email address</h2>

  <p class="text-body-secondary mb-4">
    Just one more step! Confirm your email address to activate your Ticketio account.
  </p>

  <form method="post" action="/verify-email">
    <input type="hidden" name="_token" value="{{ $csrf }}">
    <input type="hidden" name="token" value="{{ $token }}">

    <button type="submit" class="btn btn-primary w-100">
      Verify email address
    </button>
  </form>
@endsection
