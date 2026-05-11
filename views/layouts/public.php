<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="GI Smartanalytics Portal — Smart analytics and compliance solutions for modern organisations.">
  <title><?= htmlspecialchars($pageTitle ?? 'GI Smartanalytics Portal') ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚖</text></svg>">
</head>
<body>

<!-- Public Navigation -->
<nav class="public-nav">
  <div class="public-nav-inner">
    <a href="/" class="nav-logo">
      <div class="nav-logo-icon">GI</div>
      <span>GI <span>Smartanalytics</span></span>
    </a>
    <div class="nav-links">
      <a href="/auth/login" class="btn btn-ghost btn-sm">Log In</a>
      <a href="/auth/register" class="btn btn-primary btn-sm">Get Started</a>
    </div>
  </div>
</nav>

<!-- Flash Messages -->
<?php
use GI\Core\Session;
$flashError   = Session::getFlash('error');
$flashSuccess = Session::getFlash('success');
if ($flashError || $flashSuccess): ?>
<div style="max-width:1280px;margin:1rem auto;padding:0 2rem;">
  <?php if ($flashError): ?>
    <div class="alert alert-error" data-auto-dismiss="6000">
      ⚠ <?= htmlspecialchars($flashError) ?>
      <button class="alert-close" style="margin-left:auto;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;">&times;</button>
    </div>
  <?php endif; ?>
  <?php if ($flashSuccess): ?>
    <div class="alert alert-success" data-auto-dismiss="5000">
      ✓ <?= htmlspecialchars($flashSuccess) ?>
      <button class="alert-close" style="margin-left:auto;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;">&times;</button>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Page Content -->
<?= $content ?>

<?php if (empty($hidePublicFooter)): ?>
<!-- Footer -->
<footer>
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="nav-logo">
        <div class="nav-logo-icon">GI</div>
        <span>GI <span>Smartanalytics</span></span>
      </div>
      <p class="mt-2">Enterprise-grade governance, compliance, and analytics intelligence platform for modern organisations.</p>
    </div>
    <div class="footer-col">
      <h5>Platform</h5>
      <ul>
        <li><a href="/#products">Products</a></li>
        <li><a href="/#pricing">Pricing</a></li>
        <li><a href="/auth/register">Get Started</a></li>
        <li><a href="/auth/login">Log In</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Company</h5>
      <ul>
        <li><a href="#">About</a></li>
        <li><a href="#">Privacy Policy</a></li>
        <li><a href="#">Terms of Service</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> GI Smart Analytics. All rights reserved.</span>
    <span>my.gismartanalytics.com</span>
  </div>
</footer>
<?php endif; ?>

<script src="/assets/js/app.js"></script>
<script>
window.__GI_CONTEXT = window.__GI_CONTEXT || { user: null };
</script>
</body>
</html>
