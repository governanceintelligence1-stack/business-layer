<?php
$pageTitle = 'Complete Profile';
$organisationName = (string) ($user['organisation_name'] ?? $user['organisation']['name'] ?? '');
$role = (string) ($user['membership_role'] ?? $user['role'] ?? 'member');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Complete Profile</h1>
    <p class="page-subtitle">Finish your profile before continuing to the workspace.</p>
  </div>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-header">
    <h3 class="card-title">Profile Details</h3>
  </div>
  <form method="POST" action="/profile/complete">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">

    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Organisation</label>
        <input class="form-control" value="<?= htmlspecialchars($organisationName !== '' ? $organisationName : 'Assigned organisation') ?>" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <input class="form-control" value="<?= htmlspecialchars(ucfirst($role)) ?>" readonly>
      </div>
    </div>

    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">First name</label>
        <input class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Last name</label>
        <input class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Phone number</label>
        <input class="form-control" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Job title</label>
        <input class="form-control" name="job_title" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Department</label>
        <input class="form-control" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Timezone</label>
        <input class="form-control" name="timezone" value="<?= htmlspecialchars($user['timezone'] ?? 'Africa/Johannesburg') ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Locale</label>
      <input class="form-control" name="locale" value="<?= htmlspecialchars($user['locale'] ?? 'en-ZA') ?>">
    </div>

    <button type="submit" class="btn btn-primary">Save Profile</button>
  </form>
</div>
