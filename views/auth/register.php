<?php
$pageTitle = 'Register';
$hidePublicFooter = true;
?>

<div class="auth-wrapper">
  <div class="auth-container">
    <div class="auth-box" style="max-width:640px;">
      <div class="auth-logo">
        <?php $logoSize = 48; include BASE_PATH . '/views/partials/brand-logo.php'; ?>
        <div>GI <span>Smartanalytics</span></div>
      </div>
      <h1 class="auth-title">Demo SSO Registration</h1>
      <?php if (!empty($inviteIsActive) && !empty($inviteOrganisationName)): ?>
      <p class="auth-sub">You are joining <strong><?= htmlspecialchars($inviteOrganisationName) ?></strong>. Use the invited email address below.</p>
      <?php else: ?>
      <p class="auth-sub">Create a local SSO-style identity for end-to-end testing.</p>
      <?php endif; ?>
      <?php require __DIR__ . '/components/register-form.php'; ?>
    </div>
  </div>
</div>
