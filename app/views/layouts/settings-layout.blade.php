@extends('layouts.app-layout')

@section('content')
  <div class="container-fluid px-3 px-lg-5 py-4">

    <!-- Settings Header -->
    <div class="mb-4">
      <h1 class="h3 mb-1">Settings</h1>
      <p class="text-body-secondary mb-0">
        Manage your account and preferences.
      </p>
    </div>

    <div class="row g-4">

      <!-- Settings Navigation -->
      <aside class="col-12 col-lg-3 col-xl-2">
        <nav class="nav flex-column gap-1" aria-label="Account settings">

          <div class="small fw-semibold text-body-secondary px-3 mb-2">
            Personal
          </div>

          <a href="/settings/profile"
             class="nav-link rounded {{ ($settingsPage ?? '') === 'profile' ? 'active bg-body-secondary fw-semibold' : 'text-body' }}"
             @if(($settingsPage ?? '') === 'profile') aria-current="page" @endif>
            <i class="bi bi-person me-2"></i>
            Profile
          </a>

          <span class="nav-link text-body-secondary opacity-50">
            <i class="bi bi-person-gear me-2"></i>
            Account
          </span>

          <span class="nav-link text-body-secondary opacity-50">
            <i class="bi bi-palette me-2"></i>
            Appearance
          </span>

          <span class="nav-link text-body-secondary opacity-50">
            <i class="bi bi-bell me-2"></i>
            Notifications
          </span>

          <a href="/settings/github"
             class="nav-link rounded {{ ($settingsPage ?? '') === 'github' ? 'active bg-body-secondary fw-semibold' : 'text-body' }}"
             @if(($settingsPage ?? '') === 'github') aria-current="page" @endif>
            <i class="bi bi-github me-2"></i>
            Connected accounts
          </a>
          <hr class="my-3">

          <div class="small fw-semibold text-body-secondary px-3 mb-2">
            Security
          </div>

          <span class="nav-link text-body-secondary opacity-50">
            <i class="bi bi-shield-lock me-2"></i>
            Password & security
          </span>

          <span class="nav-link text-body-secondary opacity-50">
            <i class="bi bi-display me-2"></i>
            Sessions
          </span>

        </nav>
      </aside>

      <!-- Settings Content -->
      <div class="col-12 col-lg-9 col-xl-10 pt-lg-4">
        <div class="mx-auto" style="max-width: 960px;">
          @yield('settingsContent')
        </div>
      </div>

    </div>

  </div>
@endsection
