<?php
$pageTitle = 'Organisation Invitation';
$status = strtolower((string) ($invite['status'] ?? 'unknown'));
$snapshot = $invite['organisation_snapshot'] ?? $invite['organisation'] ?? [];
if (is_string($snapshot)) {
    $decoded = json_decode($snapshot, true);
    $snapshot = is_array($decoded) ? $decoded : [];
}
if (!is_array($snapshot)) {
    $snapshot = [];
}
$organisation = is_array($snapshot['organisation'] ?? null) ? $snapshot['organisation'] : $snapshot;
$organisationName = (string) (
    $organisation['name']
    ?? $organisation['organisation_name']
    ?? $invite['organisation_name']
    ?? 'Organisation'
);
$role = (string) ($invite['invited_role'] ?? $invite['role'] ?? 'member');
$email = (string) ($invite['invited_email'] ?? $invite['email'] ?? '');
$expiresAt = (string) ($invite['expires_at'] ?? '');
$isUsable = $invite !== false && $status === 'pending';
?>

<main class="<?= !empty($isAuthenticated) ? '' : 'public-page' ?>" style="max-width:860px;margin:0 auto;padding:3rem 2rem;">
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="card-title" style="font-size:1.5rem;">Organisation Invitation</h1>
        <p style="color:var(--text-muted);margin-top:.35rem;">Review the invitation before joining the organisation.</p>
      </div>
      <span class="badge badge-<?= $isUsable ? 'pending' : 'revoked' ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
    </div>

    <?php if ($invite === false): ?>
      <div class="empty-state">
        <div class="empty-state-icon">INVITE</div>
        <h3>Invitation not found</h3>
        <p>This invitation link is invalid or no longer available.</p>
      </div>
    <?php else: ?>
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <input class="form-control" value="<?= htmlspecialchars($organisationName) ?>" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <input class="form-control" value="<?= htmlspecialchars(ucfirst($role)) ?>" readonly>
        </div>
      </div>
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label">Invited email</label>
          <input class="form-control" value="<?= htmlspecialchars($email) ?>" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Expires</label>
          <input class="form-control" value="<?= htmlspecialchars(substr($expiresAt, 0, 19)) ?>" readonly>
        </div>
      </div>

      <?php if (!$isUsable): ?>
        <div class="alert alert-error">This invitation cannot be accepted because it is <?= htmlspecialchars($status) ?>.</div>
      <?php elseif (!empty($isAuthenticated)): ?>
        <form method="POST" action="/invite/<?= rawurlencode($token) ?>/accept">
          <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
          <button type="submit" class="btn btn-primary">Accept Invitation</button>
        </form>
      <?php else: ?>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
          <a class="btn btn-primary" href="/auth/login?invite=<?= rawurlencode($token) ?>">Log In to Accept</a>
          <a class="btn btn-ghost" href="/auth/register?invite=<?= rawurlencode($token) ?>">Register with This Email</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
