@extends('layouts.!master')

@section('title', 'Dashboard - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1>Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, {{ $name }}!</p>
      </div>

      <form method="post" action="/logout">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <button type="submit" class="btn btn-outline-danger">
          Sign out
        </button>
      </form>
    </div>

    <div class="alert alert-success">
      You're successfully authenticated with Ticketio.
    </div>
  </div>
@endsection

@dummy <script> @enddummy

@section('documentReady')
  console.log('Dashboard initialised');
@endsection
