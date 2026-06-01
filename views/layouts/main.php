<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Portal') ?> — GI Portal</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" type="image/png" href="/assets/images/gi-logo.png">
</head>
<body>

<?php
use GI\Core\Session;
$currentUser  = $user ?? Session::get('user') ?? [];
$userInitials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));
$userFullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: 'User';
$userRole     = ucfirst($currentUser['role'] ?? 'member');
$currentUri   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isProductsOpen = str_starts_with((string) $currentUri, '/products');

function isActiveLink(string $path, string $current): string {
    return str_starts_with($current, $path) ? 'active' : '';
}
?>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:49;"></div>

<div class="app-layout">

  <!-- Sidebar -->
  <?php include BASE_PATH . '/views/partials/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content">
    <?php
    $flashError   = Session::getFlash('error');
    $flashSuccess = Session::getFlash('success');
    $newApiKey    = Session::getFlash('new_api_key');
    ?>

    <!-- Flash Messages -->
    <?php if ($flashError || $flashSuccess || $newApiKey): ?>
    <div style="padding:1rem 2rem 0;">
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
      <?php if ($newApiKey): ?>
        <div class="alert alert-info">
          <div>
            <strong>Your new API Key (copy now — it won't be shown again):</strong>
            <div class="code-block mt-1" id="new-api-key-value"><?= htmlspecialchars($newApiKey) ?></div>
            <button class="btn btn-ghost btn-sm mt-1" data-copy="#new-api-key-value">Copy Key</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="page-content">
      <?= $content ?>
    </main>

  </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<script src="/assets/js/app.js"></script>
<script>
window.__GI_CONTEXT = {
  user: {
    id: <?= json_encode($currentUser['id'] ?? null) ?>,
    organisation_id: <?= json_encode($currentUser['organisation_id'] ?? null) ?>,
    email: <?= json_encode($currentUser['email'] ?? null) ?>,
    first_name: <?= json_encode($currentUser['first_name'] ?? null) ?>,
    last_name: <?= json_encode($currentUser['last_name'] ?? null) ?>,
    role: <?= json_encode($currentUser['role'] ?? null) ?>
  }
};
</script>
<script>
// Show mobile toggle on small screens
if (window.innerWidth <= 768) {
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) sidebarToggle.style.display = 'inline-flex';
}
window.addEventListener('resize', () => {
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) sidebarToggle.style.display = window.innerWidth <= 768 ? 'inline-flex' : 'none';
});
</script>
<!-- Logout confirmation modal -->
<div id="logout-confirm-modal" class="modal-overlay hidden">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
    <div class="modal-header">
      <h2 id="logout-modal-title">Confirm sign out</h2>
      <button class="modal-close" aria-label="Close">&times;</button>
    </div>
    <div style="margin-bottom:1rem; color:var(--text-muted);">
      Are you sure you want to sign out? You will need to sign in again to access your account.
    </div>
    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
      <button class="btn" data-modal-close>Cancel</button>
      <a id="confirm-logout-btn" href="/auth/logout" class="btn btn-primary">Sign Out</a>
    </div>
  </div>
</div>
</body>
</html>
