@extends('layouts.auth-layout')

@section('title', 'Reset Password - Ticketio')

@section('content')
  <h2 class="h4 mb-2">Reset your password</h2>

  <p class="text-body-secondary mb-4">
    Choose a new password for your Ticketio account.
  </p>

  @if($error ?? false)
    <div class="alert alert-danger" role="alert">
      {{ $error }}
    </div>
  @endif

  <form method="post" action="/reset-password">
    <input type="hidden" name="_token" value="{{ $csrf }}">
    <input type="hidden" name="token" value="{{ $token }}">

    <!-- New Password -->
    <div class="mb-3">
      <label for="password" class="form-label">New password</label>

      <div class="input-group">
        <input type="password" class="form-control" id="password"
               name="password" minlength="8"
               autocomplete="new-password"
               aria-describedby="passwordRequirements" required>

        <button type="button" class="btn btn-outline-secondary"
                data-toggle-password="password"
                aria-label="Show password" aria-pressed="false">
          <i class="bi bi-eye"></i>
        </button>
      </div>

      <!-- Password Requirements -->
      <div class="small mt-3" id="passwordRequirements">
        <div class="mb-1 text-body-secondary" id="passwordLength">
          <i class="bi bi-circle me-2"></i>At least 8 characters
        </div>

        <div class="mb-1 text-body-secondary" id="passwordNumber">
          <i class="bi bi-circle me-2"></i>Contains a number
        </div>

        <div class="text-body-secondary" id="passwordSpecial">
          <i class="bi bi-circle me-2"></i>Contains a special character
        </div>
      </div>
    </div>

    <!-- Confirm New Password -->
    <div class="mb-4">
      <label for="password_confirmation" class="form-label">
        Confirm new password
      </label>

      <div class="input-group">
        <input type="password" class="form-control"
               id="password_confirmation"
               name="password_confirmation"
               autocomplete="new-password" required>

        <button type="button" class="btn btn-outline-secondary"
                data-toggle-password="password_confirmation"
                aria-label="Show confirmation password"
                aria-pressed="false">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      Reset password
    </button>
  </form>

  <p class="text-center mt-4 mb-0">
    <a href="/login">Back to login</a>
  </p>
@endsection

@dummy <script> @enddummy

@section('documentReady')
  const passwordInput = document.getElementById('password');

  const updatePassword = () => {
    const password = passwordInput.value;

    const requirements = [
      {
        id: 'passwordLength',
        valid: Array.from(password).length >= 8
      },
      {
        id: 'passwordNumber',
        valid: /[0-9]/.test(password)
      },
      {
        id: 'passwordSpecial',
        valid: /[^a-zA-Z0-9\s]/.test(password)
      }
    ];

    requirements.forEach(requirement => {
      const element = document.getElementById(requirement.id);

      element.classList.toggle('text-success', requirement.valid);
      element.classList.toggle('text-body-secondary', !requirement.valid);

      element.querySelector('i').className = requirement.valid
        ? 'bi bi-check-circle-fill me-2'
        : 'bi bi-circle me-2';
    });

    passwordInput.setCustomValidity(
      new TextEncoder().encode(password).length > 72
        ? 'Password is too long.'
        : ''
    );
  };

  passwordInput.addEventListener('input', updatePassword);

  // --- Show / Hide Passwords ---
  document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      const visible = input.type === 'password';

      input.type = visible ? 'text' : 'password';

      button.querySelector('i').className = visible
        ? 'bi bi-eye-slash'
        : 'bi bi-eye';

      button.setAttribute(
        'aria-label',
        visible ? 'Hide password' : 'Show password'
      );

      button.setAttribute('aria-pressed', String(visible));
    });
  });

  updatePassword();
@endsection
