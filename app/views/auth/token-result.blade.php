@extends('layouts.auth-layout')

@section('title', 'Account Security - Ticketio')

@section('content')
  <div class="text-center">
    <h2 class="h4 mb-3">Account security</h2>

    <p class="text-body-secondary mb-4">
      {{ $message }}
    </p>

    <a href="/login" class="btn btn-primary">
      Return to login
    </a>
  </div>
@endsection
