@extends('layouts.app-layout')

@section('title', 'Dashboard - Ticketio')

@section('content')
  <div class="container-fluid py-4">

    <!-- Dashboard Header -->
    <div class="mb-4">
      <h1 class="h3 mb-2">Dashboard</h1>
      <p class="text-body-secondary mb-0">
        Welcome back, {{ $name }}!
      </p>
    </div>

    <!-- Dashboard Content -->
    <div class="alert alert-success" role="status">
      <i class="bi bi-check-circle me-2"></i>
      You're successfully authenticated with Ticketio.
    </div>

  </div>
@endsection

@dummy <script> @enddummy

@section('documentReady')
  console.log('Dashboard initialised');
@endsection
