@extends('layouts.settings-layout')

@section('title', 'GitHub Connection - Ticketio')

@section('settingsContent')
  <div class="mb-4">
    <h2 class="h4 mb-1">Connected accounts</h2>
    <p class="text-body-secondary mb-0">Connect GitHub to sign in using your existing Ticketio account.</p>
  </div>

  @if($success ?? false)
    <div class="alert alert-success" role="status">{{ $success }}</div>
  @endif

  @if($error ?? false)
    <div class="alert alert-danger" role="alert">{{ $error }}</div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-3 mb-3">
        <i class="bi bi-github fs-2"></i>
        <div>
          <h3 class="h5 mb-1">GitHub</h3>
          <p class="text-body-secondary small mb-0">Sign in with GitHub, without changing your Ticketio account.</p>
        </div>
      </div>

      @if($connection !== null)
        <p class="mb-3">
          <span class="badge text-bg-success me-2">Connected</span>
          <a href="https://github.com/{{ $connection['GITHUB_USERNAME'] }}" target="_blank" rel="noopener noreferrer">
            {{ $connection['GITHUB_USERNAME'] }}
          </a>
        </p>
        <p class="text-body-secondary small">To disconnect, confirm your Ticketio password. If you signed up with GitHub, <a href="/settings/password">set a Ticketio password</a> first.</p>
        <form method="post" action="/settings/github/disconnect">
          <input type="hidden" name="_token" value="{{ $csrf }}">
          <div class="mb-3">
            <label for="github_disconnect_password" class="form-label">Ticketio password</label>
            <input type="password" id="github_disconnect_password" name="password" class="form-control" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-outline-danger">Disconnect GitHub</button>
        </form>
      @else
        <p class="text-body-secondary">Not connected. Connect GitHub to enable GitHub sign-in for this account.</p>
        <form method="post" action="/settings/github/connect">
          <input type="hidden" name="_token" value="{{ $csrf }}">
          <div class="mb-3">
            <label for="github_connect_password" class="form-label">Confirm your Ticketio password</label>
            <input type="password" id="github_connect_password" name="password" class="form-control" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-github me-2"></i> Connect GitHub
          </button>
        </form>
      @endif
    </div>
  </div>
@endsection
