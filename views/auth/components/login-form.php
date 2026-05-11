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
  .login-actions {
    display: flex;
    flex-direction: column;
    gap: .75rem;
  }
</style>

<div class="login-note">
  You will be redirected to our secure identity provider to authenticate.
</div>

<div class="login-actions">
  <a href="/auth/login" class="btn btn-primary w-100">Continue with Keycloak SSO</a>
  <div class="auth-footer">
    Don't have an account? <a href="/auth/register">Register your organisation</a>
  </div>
</div>
