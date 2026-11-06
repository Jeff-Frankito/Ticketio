@extends('layouts.!master')

@section('body')
  <div class="d-flex flex-column min-vh-100">

    <!-- Application Header -->
    <header class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
      <div class="container-fluid">

        <a class="navbar-brand fw-semibold" href="/dashboard">
          Ticketio
        </a>

        <div class="d-flex align-items-center gap-3 ms-auto">

          @yield('navbar')

          <form method="post" action="/logout" class="mb-0">
            <input type="hidden" name="_token" value="{{ $csrf ?? '' }}">

            <button type="submit" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-box-arrow-right me-1"></i>
              Sign out
            </button>
          </form>

        </div>
      </div>
    </header>

    <!-- Application Content -->
    <main class="flex-grow-1">
      @yield('content')
    </main>

    <!-- Application Footer -->
    <footer class="border-top py-3">
      <div class="container-fluid d-flex justify-content-between align-items-center">

        <span class="text-body-secondary small">
          &copy; {{ date('Y') }} Ticketio
        </span>

        <span class="text-body-secondary small">
          @yield('footer')
        </span>

      </div>
    </footer>

  </div>
@endsection
