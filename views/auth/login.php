<?php
$pageTitle = 'Log In';
$hidePublicFooter = true;
?>

<div class="auth-wrapper">
  <div class="auth-container">
    <div class="auth-box" style="max-width:640px;">
      <div class="auth-logo">
        <?php $logoSize = 48; include BASE_PATH . '/views/partials/brand-logo.php'; ?>
        <div>GI <span>Smartanalytics</span></div>
      </div>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Sign in to your GI Smartanalytics Portal account.</p>
      <?php require __DIR__ . '/components/login-form.php'; ?>
    </div>
  </div>
</div>
