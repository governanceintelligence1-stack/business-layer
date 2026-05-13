<?php $pageTitle = 'My Profile'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Your account identity and access details.</p>
  </div>
</div>

<div class="card" style="max-width: 720px;">
  <div class="card-header">
    <h3 class="card-title">Profile Information</h3>
  </div>
  <div class="form-row form-row-2">
    <div class="form-group">
      <label class="form-label">First Name</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars((string)($user['first_name'] ?? '')) ?>" readonly>
    </div>
    <div class="form-group">
      <label class="form-label">Last Name</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars((string)($user['last_name'] ?? '')) ?>" readonly>
    </div>
  </div>
  <div class="form-group">
    <label class="form-label">Email</label>
    <input type="text" class="form-control" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>" readonly>
  </div>
  <div class="form-row form-row-2">
    <div class="form-group">
      <label class="form-label">Role</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst((string)($user['role'] ?? 'member'))) ?>" readonly>
    </div>
    <div class="form-group">
      <label class="form-label">Organisation ID</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars((string)($user['organisation_id'] ?? '')) ?>" readonly>
    </div>
  </div>
</div>

