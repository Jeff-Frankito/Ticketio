
@extends('layouts.!master')

@section('body')
  <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">

    <div class="w-100" style="max-width: 440px;">

      <!-- Branding -->
      <div class="text-center mb-4">
        <a href="/" class="text-decoration-none text-body">
          <h1 class="h2 fw-bold mb-0">Ticketio</h1>
        </a>
      </div>

      <!-- Authentication Content -->
      <div class="card shadow-sm">
        <div class="card-body p-4">

          @yield('content')

        </div>
      </div>

      <!-- Footer -->
      <div class="text-center text-body-secondary small mt-4">
        &copy; {{ date('Y') }} Ticketio
      </div>

    </div>

  </main>

  <!-- Theme Toggle -->
  <button type="button"
          class="btn btn-outline-secondary rounded-circle position-fixed top-0 end-0 m-4 d-inline-flex align-items-center justify-content-center"
          id="themeToggle"
          style="width: 40px; height: 40px; z-index: 1050;"
          aria-label="Toggle theme"
          title="Toggle theme">
    <i class="bi bi-sun-fill"></i>
  </button>
@endsection

@dummy <script> @enddummy

@section('documentReady')
  const themeToggle = document.getElementById('themeToggle');

  // --- Update Theme Icon ---
  const updateThemeIcon = () => {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    themeToggle.querySelector('i').className = dark
      ? 'bi bi-sun-fill'
      : 'bi bi-moon-stars-fill';

    themeToggle.setAttribute(
      'aria-label',
      dark ? 'Switch to light mode' : 'Switch to dark mode'
    );

    themeToggle.setAttribute(
      'title',
      dark ? 'Switch to light mode' : 'Switch to dark mode'
    );
  };

  // --- Toggle Theme ---
  themeToggle.addEventListener('click', () => {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const theme = dark ? 'light' : 'dark';

    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('ticketio-theme', theme);

    updateThemeIcon();
  });

  // --- Initialise Icon ---
  updateThemeIcon();
@endsection
