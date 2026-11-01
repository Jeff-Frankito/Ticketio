@extends('layouts.!master')

@section('title', 'Account Security - Ticketio')

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4 text-center">

            <h2 class="mb-3">Account security</h2>

            <p class="mb-4">
              {{ $message }}
            </p>

            <a href="/login" class="btn btn-primary">
              Return to login
            </a>

          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
