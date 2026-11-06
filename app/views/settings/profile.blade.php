
@extends('layouts.settings-layout')

@section('title', 'Profile Settings - Ticketio')

@section('settingsContent')
  <h2 class="h4 mb-3">Public profile</h2>
  <hr class="mb-4">

  <form method="post" action="/settings/profile">
    <input type="hidden" name="_token" value="{{ $csrf }}">

    <!-- Success Message -->
    @if($success ?? false)
      <div class="alert alert-success" role="status">
        <i class="bi bi-check-circle me-2"></i>
        Your profile has been updated successfully.
      </div>
    @endif

    <!-- Validation Errors -->
    @if(!empty($errors))
      <div class="alert alert-danger" role="alert">
        <div class="fw-semibold mb-2">
          <i class="bi bi-exclamation-circle me-1"></i>
          Please correct the following:
        </div>

        <ul class="mb-0">
          @foreach($errors as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="row g-4">

      <!-- Profile Information -->
      <div class="col-12 col-lg-8">

        <!-- First & Last Name -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label for="first_name" class="form-label">First name</label>

            <input type="text"
                   class="form-control"
                   id="first_name"
                   name="FIRST_NAME"
                   minlength="1"
                   maxlength="100"
                   autocomplete="given-name"
                   value="{{ $profile['FIRST_NAME'] ?? '' }}"
                   required>
          </div>

          <div class="col-12 col-md-6">
            <label for="last_name" class="form-label">Last name</label>

            <input type="text"
                   class="form-control"
                   id="last_name"
                   name="LAST_NAME"
                   minlength="1"
                   maxlength="100"
                   autocomplete="family-name"
                   value="{{ $profile['LAST_NAME'] ?? '' }}"
                   required>
          </div>
        </div>

        <!-- Email Address -->
        <div class="mb-3">
          <label for="email" class="form-label">Email address</label>

          <div class="input-group">
            <input type="email"
                   class="form-control"
                   id="email"
                   value="{{ $profile['EMAIL'] ?? '' }}"
                   autocomplete="email"
                   readonly
                   aria-describedby="emailHelp">

            <span class="input-group-text">
              <i class="bi bi-lock" aria-hidden="true"></i>
            </span>
          </div>

          <div id="emailHelp" class="form-text">
            Your email address is managed under Account settings.
          </div>
        </div>

        <!-- Public Profile Divider -->
        <hr class="my-4">

        <!-- Display Name -->
        <div class="mb-3">
          <label for="display_name" class="form-label">Display name</label>

          <input type="text"
                 class="form-control"
                 id="display_name"
                 name="DISPLAY_NAME"
                 maxlength="150"
                 value="{{ $profile['DISPLAY_NAME'] ?? '' }}"
                 placeholder="Your display name">

          <div class="form-text">
            This is how other project members will see you.
          </div>
        </div>

        <!-- Job Title & Company -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label for="job_title" class="form-label">Job title</label>

            <input type="text"
                   class="form-control"
                   id="job_title"
                   name="JOB_TITLE"
                   maxlength="150"
                   value="{{ $profile['JOB_TITLE'] ?? '' }}"
                   placeholder="e.g. Software Developer">
          </div>

          <div class="col-12 col-md-6">
            <label for="company" class="form-label">Company</label>

            <input type="text"
                   class="form-control"
                   id="company"
                   name="COMPANY"
                   maxlength="150"
                   value="{{ $profile['COMPANY'] ?? '' }}"
                   placeholder="Company or organisation">
          </div>
        </div>

        <!-- Bio -->
        <div class="mb-3">
          <label for="bio" class="form-label">Bio</label>

          <textarea class="form-control"
                    id="bio"
                    name="BIO"
                    rows="4"
                    maxlength="1000"
                    placeholder="Tell people a little about yourself...">{{ $profile['BIO'] ?? '' }}</textarea>

          <div class="form-text">
            A short introduction that other project members can see.
          </div>
        </div>

        <!-- Website -->
        <div class="mb-3">
          <label for="website" class="form-label">Website</label>

          <input type="url"
                 class="form-control"
                 id="website"
                 name="WEBSITE"
                 maxlength="500"
                 value="{{ $profile['WEBSITE'] ?? '' }}"
                 placeholder="https://example.com">
        </div>

        <!-- GitHub Username -->
        <div class="mb-3">
          <label for="github_username" class="form-label">
            <i class="bi bi-github me-1"></i>
            GitHub username
          </label>

          <div class="input-group">
            <span class="input-group-text">github.com/</span>

            <input type="text"
                   class="form-control"
                   id="github_username"
                   name="GITHUB_USERNAME"
                   maxlength="39"
                   pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*"
                   title="Use letters, numbers and single hyphens. Hyphens cannot appear at the beginning or end."
                   value="{{ $profile['GITHUB_USERNAME'] ?? '' }}"
                   placeholder="username"
                   autocomplete="off">
          </div>

          <div class="form-text">
            Other project members can use this to find your GitHub profile.
          </div>
        </div>

        <!-- Location -->
        <div class="mb-4">
          <label for="location" class="form-label">Location</label>

          <input type="text"
                 class="form-control"
                 id="location"
                 name="LOCATION"
                 maxlength="150"
                 value="{{ $profile['LOCATION'] ?? '' }}"
                 placeholder="City or region">
        </div>

        <!-- Save -->
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i>
          Save profile
        </button>

      </div>

      <!-- Profile Picture -->
      <div class="col-12 col-lg-4">

        <div class="d-flex flex-column align-items-center text-center">

          <div class="fw-semibold mb-3">
            Profile picture
          </div>

          <!-- Avatar -->
          <div class="rounded-circle bg-body-tertiary border overflow-hidden d-flex align-items-center justify-content-center"
               style="width: 180px; height: 180px; flex-shrink: 0;">

            @if(!empty($profile['AVATAR_PATH']))
              <img src="{{ $profile['AVATAR_PATH'] }}"
                   alt="Profile picture"
                   class="w-100 h-100 object-fit-cover">
            @else
              <i class="bi bi-person text-body-secondary"
                 style="font-size: 80px;"></i>
            @endif

          </div>

          <!-- Edit Picture -->
          <button type="button"
                  class="btn btn-outline-secondary btn-sm mt-3"
                  disabled>
            <i class="bi bi-pencil me-1"></i>
            Edit picture
          </button>

          <div class="form-text mt-3">
            Your profile picture will appear alongside your tickets and comments.
          </div>

        </div>

      </div>

    </div>
  </form>
@endsection
