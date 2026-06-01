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
      <h1 class="auth-title">Register Your Organisation</h1>
      <p class="auth-sub">Set up your Governance Intelligence Portal account.</p>
      <?php require __DIR__ . '/components/register-form.php'; ?>
    </div>
  </div>
</div>
