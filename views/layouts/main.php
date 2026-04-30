<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Portal') ?> — GI Portal</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚖</text></svg>">
</head>
<body>

<?php
use GI\Core\Session;
$currentUser  = $user ?? Session::get('user') ?? [];
$userInitials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));
$userFullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: 'User';
$userRole     = ucfirst($currentUser['role'] ?? 'member');
$currentUri   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function isActiveLink(string $path, string $current): string {
    return str_starts_with($current, $path) ? 'active' : '';
}
?>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:49;"></div>

<div class="app-layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="nav-logo-icon">GI</div>
      <span>Governance <span>Intelligence</span></span>
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-section">Main</div>
      <a href="/dashboard" class="sidebar-link <?= isActiveLink('/dashboard', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="/organisation" class="sidebar-link <?= isActiveLink('/organisation', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M9 21V7l6-4v18M9 7H3v14"/></svg>
        Organisation
      </a>

      <div class="sidebar-section">Products & Plans</div>
      <a href="/products" class="sidebar-link <?= isActiveLink('/products', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Products
      </a>
      <a href="/plans" class="sidebar-link <?= isActiveLink('/plans', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Plans
      </a>
      <a href="/subscriptions" class="sidebar-link <?= isActiveLink('/subscriptions', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Subscriptions
      </a>

      <div class="sidebar-section">Usage</div>
      <a href="/credits" class="sidebar-link <?= isActiveLink('/credits', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Credits
      </a>
      <a href="/api-keys" class="sidebar-link <?= isActiveLink('/api-keys', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        API Keys
      </a>
      <a href="/billing" class="sidebar-link <?= isActiveLink('/billing', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Billing
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= htmlspecialchars($userInitials) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($userFullName) ?></div>
          <div class="sidebar-user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
      </div>
      <a href="/auth/logout" class="btn btn-ghost btn-sm w-100"
         data-confirm="Are you sure you want to log out?">
        Log Out
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Top Bar -->
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:1rem;">
        <button id="sidebar-toggle" class="btn btn-ghost btn-sm" style="display:none;">☰</button>
        <span class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Portal') ?></span>
      </div>
      <div class="topbar-actions">
        <?php
        $flashError   = Session::getFlash('error');
        $flashSuccess = Session::getFlash('success');
        $newApiKey    = Session::getFlash('new_api_key');
        ?>
        <span style="font-size:.85rem;color:var(--text-muted);"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
      </div>
    </header>

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
// Show mobile toggle on small screens
if (window.innerWidth <= 768) {
  const t = document.getElementById('sidebar-toggle');
  if (t) t.style.display = 'inline-flex';
}
window.addEventListener('resize', () => {
  const t = document.getElementById('sidebar-toggle');
  if (t) t.style.display = window.innerWidth <= 768 ? 'inline-flex' : 'none';
});
</script>
</body>
</html>
