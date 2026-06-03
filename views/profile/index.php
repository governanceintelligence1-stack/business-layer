<?php $pageTitle = 'My Profile'; ?>
<?php
$preferences = is_array($user['preferences'] ?? null) ? $user['preferences'] : [];
$displayName = (string)($user['display_name'] ?? trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : (string)($user['username'] ?? 'User');

$identityRows = [
    'Display name' => $displayName,
    'First name' => $user['first_name'] ?? '',
    'Last name' => $user['last_name'] ?? '',
    'Username' => $user['username'] ?? '',
    'Email' => $user['email'] ?? '',
    'Email verified' => !empty($user['email_verified']) ? 'Yes' : 'No',
    'Phone number' => $user['phone_number'] ?? '',
    'Job title' => $user['job_title'] ?? '',
    'Department' => $user['department'] ?? '',
    'Timezone' => $user['timezone'] ?? '',
    'Locale' => $user['locale'] ?? '',
];

$accessRows = [
    'Role' => $user['role'] ?? '',
    'Status' => $user['status'] ?? '',
    'Membership role' => $user['membership_role'] ?? '',
    'Membership status' => $user['membership_status'] ?? '',
    'SSO provider' => $user['sso_provider'] ?? '',
    'Keycloak ID' => $user['keycloak_id'] ?? $user['sso_subject_id'] ?? '',
    'Last login' => $user['last_login_at'] ?? '',
    'Last seen' => $user['last_seen_at'] ?? '',
];

$organisationRows = [
    'Organisation' => $user['organisation_name'] ?? '',
    'Slug' => $user['organisation_slug'] ?? '',
    'Organisation ID' => $user['organisation_id'] ?? '',
    'Account type' => $user['account_type'] ?? '',
    'Status' => $user['organisation_status'] ?? '',
    'Billing email' => $user['billing_email'] ?? '',
    'Tax number' => $user['tax_number'] ?? '',
    'Country' => $user['country'] ?? '',
    'Currency' => $user['currency'] ?? '',
];

$auditRows = [
    'User created' => $user['user_created_at'] ?? '',
    'User updated' => $user['user_updated_at'] ?? '',
    'Profile created' => $user['profile_created_at'] ?? '',
    'Profile updated' => $user['profile_updated_at'] ?? '',
    'Organisation created' => $user['organisation_created_at'] ?? '',
    'Organisation updated' => $user['organisation_updated_at'] ?? '',
];

$renderRows = static function (array $rows): void {
    foreach ($rows as $label => $value) {
        $value = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES);
        if ($value === '') {
            $value = '-';
        }
        ?>
        <div class="profile-field">
          <div class="profile-label"><?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="profile-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php
    }
};
?>

<style>
  .profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    max-width: 1120px;
  }
  .profile-card {
    min-width: 0;
  }
  .profile-card-wide {
    grid-column: 1 / -1;
  }
  .profile-field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }
  .profile-field {
    min-width: 0;
    padding: 0.7rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    background: var(--background);
  }
  .profile-label {
    color: var(--muted-foreground);
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }
  .profile-value {
    color: var(--foreground);
    font-size: 0.88rem;
    font-weight: 600;
    overflow-wrap: anywhere;
  }
  .preference-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .preference-pill {
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.35rem 0.6rem;
    font-size: 0.78rem;
    font-weight: 600;
    background: var(--background);
  }
  @media (max-width: 900px) {
    .profile-grid,
    .profile-field-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Your account identity, organisation, access, and preferences.</p>
  </div>
</div>

<div class="profile-grid">
  <div class="card profile-card profile-card-wide">
    <div class="card-header">
      <h3 class="card-title">Identity</h3>
    </div>
    <div class="profile-field-grid">
      <?php $renderRows($identityRows); ?>
    </div>
  </div>

  <div class="card profile-card">
    <div class="card-header">
      <h3 class="card-title">Access</h3>
    </div>
    <div class="profile-field-grid" style="grid-template-columns: 1fr;">
      <?php $renderRows($accessRows); ?>
    </div>
  </div>

  <div class="card profile-card">
    <div class="card-header">
      <h3 class="card-title">Organisation</h3>
    </div>
    <div class="profile-field-grid" style="grid-template-columns: 1fr;">
      <?php $renderRows($organisationRows); ?>
    </div>
  </div>

  <div class="card profile-card profile-card-wide">
    <div class="card-header">
      <h3 class="card-title">Preferences</h3>
    </div>
    <?php if ($preferences === []): ?>
      <div class="profile-value">No preferences set.</div>
    <?php else: ?>
      <div class="preference-list">
        <?php foreach ($preferences as $key => $value): ?>
          <?php
          $display = is_bool($value)
              ? ($value ? 'Yes' : 'No')
              : (is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES));
          ?>
          <span class="preference-pill">
            <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$key)), ENT_QUOTES, 'UTF-8') ?>:
            <?= htmlspecialchars($display, ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card profile-card profile-card-wide">
    <div class="card-header">
      <h3 class="card-title">Audit</h3>
    </div>
    <div class="profile-field-grid">
      <?php $renderRows($auditRows); ?>
    </div>
  </div>
</div>
