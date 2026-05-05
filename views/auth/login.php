<?php $pageTitle = 'Log In'; ?>

<div class="auth-wrapper">
  <nav class="public-nav">
    <div class="public-nav-inner">
      <a href="/" class="nav-logo">
        <div class="nav-logo-icon">GI</div>
        <span>Governance <span>Intelligence</span></span>
      </a>
    </div>
  </nav>

  <div class="auth-container">
    <div class="auth-box">
      <div class="auth-logo">
        <div class="nav-logo-icon" style="margin:0 auto .75rem;">GI</div>
      </div>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Sign in to the Governance Intelligence Portal</p>

      <div style="text-align:center;">
        <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:1.5rem;">
          You will be redirected to our secure identity provider to authenticate.
        </p>
        <a href="/auth/login" class="btn btn-primary btn-lg w-100">
          🔐 Continue with Keycloak SSO
        </a>
      </div>

      <div class="auth-divider">or</div>

      <div class="auth-footer">
        Don't have an account?
        <a href="/auth/register">Register your organisation</a>
      </div>
    </div>
  </div>
</div>
