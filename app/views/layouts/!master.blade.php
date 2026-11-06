
<!DOCTYPE html>
<html lang="@yield('lang', 'en')">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>@yield('title', 'Ticketio')</title>

  <!-- Initialise Theme Before CSS Loads -->
  <script>
    (() => {
      const theme = localStorage.getItem('ticketio-theme') || 'dark';
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>

  @include('layouts.!includes', ['libraries' => []])

  @yield('meta')
  @yield('styles')
  @yield('head')

</head>
<body>

  @yield('body')

  <script>
    'use strict';

    // --- Document Ready ---
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', () => {
        @yield('documentReady')
      }, { once: true });
    } else {
      @yield('documentReady')
    }

    // --- Window Loaded ---
    if(document.readyState === 'complete'){
      @yield('windowLoad')
    } else {
      window.addEventListener('load', () => {
        @yield('windowLoad')
      }, { once: true });
    }

    // --- Page Show ---
    window.addEventListener('pageshow', event => {
      @yield('pageShow')
    });

    // --- Page Hide ---
    window.addEventListener('pagehide', event => {
      @yield('pageHide')
    });

    // --- Visibility Change ---
    document.addEventListener('visibilitychange', event => {
      @yield('visibilityChange')
    });

    // --- Online ---
    window.addEventListener('online', event => {
      @yield('online')
    });

    // --- Offline ---
    window.addEventListener('offline', event => {
      @yield('offline')
    });

    // --- Form Submit ---
    document.addEventListener('submit', event => {
      @yield('formSubmit')
    });

    // --- Input ---
    document.addEventListener('input', event => {
      @yield('input')
    });

    // --- Change ---
    document.addEventListener('change', event => {
      @yield('change')
    });

    // --- Click ---
    document.addEventListener('click', event => {
      @yield('click')
    });

    // --- Key Down ---
    document.addEventListener('keydown', event => {
      @yield('keyDown')
    });

    // --- Window Resize ---
    window.addEventListener('resize', event => {
      @yield('resize')
    });

    // --- Window Scroll ---
    window.addEventListener('scroll', event => {
      @yield('scroll')
    }, { passive: true });

    // --- Browser History ---
    window.addEventListener('popstate', event => {
      @yield('popState')
    });

    // --- Storage Change ---
    window.addEventListener('storage', event => {
      @yield('storage')
    });
  </script>

  @yield('scripts')

</body>
</html>
