<style>
  body { overflow: hidden; }
  .auth-container {
    min-height: calc(100vh - 76px);
    padding: .75rem 1rem !important;
    align-items: center !important;
  }
  .auth-box {
    max-height: calc(100vh - 98px);
    overflow: hidden;
    padding: 1.5rem;
  }
  .auth-title { margin-bottom: .25rem; }
  .auth-sub { margin-bottom: .9rem; }
  .login-note {
    color: var(--text-muted);
    font-size: .9rem;
    text-align: center;
    margin-bottom: 1rem;
  }
  .demo-sso-panel {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    background: var(--bg-card);
  }
  .demo-sso-idp {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: .9rem;
  }
  .demo-sso-idp strong { color: var(--text); }
  .demo-sso-idp span { color: var(--text-muted); font-size: .82rem; }
  .login-actions {
    display: flex;
    flex-direction: column;
    gap: .75rem;
  }
</style>

<?php if (!empty($dbLoginEnabled)): ?>
<div class="login-note">
  Test login loads an existing user from `gi_user_db` through user-api. No password is stored in this app.
</div>

<div class="demo-sso-panel">
  <div class="demo-sso-idp">
    <div>
      <strong>DB user login</strong>
      <div><span>Select an existing local user for pre-production testing</span></div>
    </div>
    <span class="badge badge-pending">Test</span>
  </div>

  <form method="POST" action="/auth/db-login" class="login-actions">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
    <div class="form-group">
      <label class="form-label">DB user email</label>
      <input class="form-control"
             type="email"
             name="email"
             value="<?= htmlspecialchars($dbLoginEmail ?? 'nomsa.khumalo@khumaloforensics.co.za') ?>"
             placeholder="name@company.co.za"
             required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Continue as DB User</button>
  </form>
</div>

<div class="auth-footer">
  Real SSO is still available when `DB_LOGIN_ENABLED=false`.
</div>
<?php else: ?>
<div class="login-note">
  Demo SSO simulates the identity-provider handoff while using users from `gi_user_db`.
</div>

<div class="demo-sso-panel">
  <div class="demo-sso-idp">
    <div>
      <strong>Keycloak SSO</strong>
      <div><span>Demo identity provider for end-to-end testing</span></div>
    </div>
    <span class="badge badge-pending">Demo</span>
  </div>

  <form method="POST" action="/auth/demo-login" class="login-actions">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
    <div class="form-group">
      <label class="form-label">SSO email</label>
      <input class="form-control"
             type="email"
             name="email"
             value="<?= htmlspecialchars($demoEmail ?? 'nomsa.khumalo@khumaloforensics.co.za') ?>"
             placeholder="name@company.co.za"
             required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Continue with Demo SSO</button>
  </form>
</div>

<div class="auth-footer">
  Need a demo account? <a href="/auth/register<?= !empty($inviteToken) ? '?invite=' . rawurlencode((string) $inviteToken) : '' ?>">Register with Demo SSO</a>
</div>
<?php endif; ?>
