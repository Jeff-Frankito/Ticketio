@extends('layouts.settings-layout')

@section('title', 'Password & security - Ticketio')

@section('settingsContent')
  <h2 class="h4 mb-3">Set or reset your password</h2>
  <p>Use a password to sign in alongside GitHub. We’ll email your account address a link to choose a new password.</p>

  @if($sent)
    <div class="alert alert-success" role="status">A password link has been requested. Check your inbox to continue.</div>
  @endif
  @if($error)
    <div class="alert alert-danger" role="alert">{{ $error }}</div>
  @endif

  <form method="post" action="/settings/password">
    <input type="hidden" name="_token" value="{{ $csrf }}">
    <button type="submit" class="btn btn-primary">Email me a password link</button>
  </form>
  <p class="text-body-secondary small mt-3">The link expires in 30 minutes. Setting a password signs out existing sessions. Your GitHub connection stays linked.</p>
@endsection
